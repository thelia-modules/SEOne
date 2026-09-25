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

namespace SEOne\Tests\Unit\Service\MetaTemplate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SEOne\Service\MetaTemplate\HtmlToPlainText;

final class HtmlToPlainTextTest extends TestCase
{
    #[Test]
    #[DataProvider('htmlProvider')]
    public function itConvertsHtmlToPlainText(?string $html, string $expected): void
    {
        self::assertSame($expected, HtmlToPlainText::convert($html));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function htmlProvider(): iterable
    {
        yield 'null' => [null, ''];
        yield 'empty string' => ['', ''];
        yield 'plain text untouched' => ['A t-shirt', 'A t-shirt'];
        yield 'tags removed' => ['<p>A <strong>great</strong> t-shirt</p>', 'A great t-shirt'];
        yield 'entities decoded' => ['Rock &amp; Roll &quot;Deluxe&quot;', 'Rock & Roll "Deluxe"'];
        yield 'html5 entity decoded' => ['Caf&eacute; &hellip; ferm&eacute;', 'Café … fermé'];
        yield 'apostrophe entity decoded' => ['L&#039;été', "L'été"];
        yield 'runs of spaces collapsed' => ["A   t-shirt\n\twith  room", 'A t-shirt with room'];
        yield 'non breaking space collapsed' => ["A\xC2\xA0t-shirt", 'A t-shirt'];
        yield 'encoded non breaking space collapsed' => ['A&nbsp;&nbsp;t-shirt', 'A t-shirt'];
        yield 'trimmed' => ["  <p> A t-shirt </p>\n", 'A t-shirt'];
        yield 'leading non breaking space trimmed' => ["\xC2\xA0A t-shirt\xC2\xA0", 'A t-shirt'];
        yield 'markup only' => ['<p><br /></p>', ''];
        yield 'zero kept' => ['0', '0'];
        yield 'paragraphs separated' => ['<p>Un</p><p>deux</p>', 'Un deux'];
        yield 'list items separated' => ['<ul><li>Bleu</li><li>Rouge</li></ul>', 'Bleu Rouge'];
        yield 'inline tags glue nothing' => ['Ro<strong>ck</strong>', 'Rock'];
    }
}
