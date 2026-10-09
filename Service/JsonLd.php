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

namespace SEOne\Service;

/**
 * A structured data block, ready to print in a page.
 *
 * Its values come from the catalogue and from listeners (a product description, a customer's review):
 * text the shop does not control. A "</script>" in one of them would end the block and let the rest
 * run as HTML. "<" and ">" are written as \u003C and \u003E (the JSON escapes of those
 * characters), which a JSON-LD parser reads back as
 * the same characters. A byte that is no UTF-8 (an old import) is replaced rather than failing the page.
 */
final class JsonLd
{
    private const int FLAGS = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR;

    /**
     * @param array<array-key, mixed> $data
     */
    public static function script(array $data): string
    {
        return '<script type="application/ld+json">'.self::encode($data).'</script>';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data, self::FLAGS);
    }
}
