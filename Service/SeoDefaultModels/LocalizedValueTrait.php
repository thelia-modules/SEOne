<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Service\SeoDefaultModels;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

/**
 * Reads i18n columns the way the rest of Thelia does: when the requested locale has no
 * translation, honour the back-office setting "Si une traduction est absente ou incomplète"
 * (config key default_lang_without_translation) instead of returning an empty value.
 */
trait LocalizedValueTrait
{
    private function localizedValue(?ActiveRecordInterface $record, string $getter, string $locale): string
    {
        if (null === $record) {
            return '';
        }

        $value = $record->setLocale($locale)->{$getter}();

        // Explicit emptiness test rather than ?: — "0" is a legitimate title.
        if (null !== $value && '' !== $value) {
            return (string) $value;
        }

        $fallbackLocale = $this->fallbackLocale($locale);

        if (null === $fallbackLocale) {
            return '';
        }

        $value = $record->setLocale($fallbackLocale)->{$getter}();

        // The record must keep serving the requested locale: getUrl() and any later read
        // are not supposed to switch language behind our back.
        $record->setLocale($locale);

        return null === $value ? '' : (string) $value;
    }

    /**
     * @param array<string> $getters
     */
    private function firstLocalizedValue(?ActiveRecordInterface $record, array $getters, string $locale): string
    {
        foreach ($getters as $getter) {
            $value = $this->localizedValue($record, $getter, $locale);

            if ('' !== $value) {
                return $value;
            }
        }

        return '';
    }

    private function fallbackLocale(string $currentLocale): ?string
    {
        // STRICTLY_USE_REQUESTED_LANGUAGE: the admin asked for no substitution at all.
        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== (int) ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return null;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return $defaultLocale === $currentLocale ? null : $defaultLocale;
    }
}
