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

/**
 * Turns a rich-text field (a product description, a category chapo) into the plain text a meta
 * tag accepts: no markup, no entity, no run of spaces and no non-breaking space, which a
 * WYSIWYG editor leaves behind and which reads as a stray character in a search result.
 */
final readonly class HtmlToPlainText
{
    public static function convert(?string $html): string
    {
        if (null === $html || '' === $html) {
            return '';
        }

        // Two paragraphs or two list items glued together read as one word once the tags are
        // gone: a block boundary is worth a space.
        $html = preg_replace('/<\/?(?:p|div|br|li|ul|ol|h[1-6]|tr|td|th|blockquote|section|article)\b[^>]*>/i', ' ', $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        // U+00A0, the "\xC2\xA0" bytes a &nbsp; decodes to, is named on its own: \s only covers
        // it when PCRE is built with Unicode properties, and trim() never strips it.
        $text = preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
