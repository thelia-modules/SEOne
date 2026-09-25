<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Service\MetaTemplate;

use SEOne\SEOne;
use SEOne\Service\SeoRequestMemo;

/**
 * Meta templates live in the module settings, next to the default title and description SEOne
 * already keeps per language: one setting per view and field, translated, plus one maximum
 * length per field shared by every language. No table of its own.
 */
final readonly class MetaTemplateRepository
{
    private const string TEMPLATE_KEY_PREFIX = 'meta_template_';
    private const string MAX_LENGTH_KEY_PREFIX = 'meta_template_max_length_';

    public function __construct(private SeoRequestMemo $seoRequestMemo)
    {
    }

    public static function templateKey(string $view, MetaTemplateField $field): string
    {
        return self::TEMPLATE_KEY_PREFIX.$view.'_'.$field->value;
    }

    public static function maxLengthKey(MetaTemplateField $field): string
    {
        return self::MAX_LENGTH_KEY_PREFIX.$field->value;
    }

    /**
     * The template configured for this view, field and language; '' when there is none.
     */
    public function getTemplate(string $view, MetaTemplateField $field, string $locale): string
    {
        return trim((string) $this->seoRequestMemo->getConfigValue(self::templateKey($view, $field), null, $locale));
    }

    public function saveTemplate(string $view, MetaTemplateField $field, string $locale, string $template): void
    {
        SEOne::setConfigValue(self::templateKey($view, $field), trim($template), $locale);
    }

    public function getMaxLength(MetaTemplateField $field): int
    {
        $value = $this->seoRequestMemo->getConfigValue(self::maxLengthKey($field));

        if (null === $value || '' === $value || !ctype_digit($value) || (int) $value <= 0) {
            return $field->defaultMaxLength();
        }

        return (int) $value;
    }

    /**
     * @param int|null $maxLength null or a non-positive value restores the field's default
     */
    public function saveMaxLength(MetaTemplateField $field, ?int $maxLength): void
    {
        SEOne::setConfigValue(
            self::maxLengthKey($field),
            null === $maxLength || $maxLength <= 0 ? '' : (string) $maxLength,
        );
    }
}
