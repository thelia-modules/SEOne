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
use SEOne\Service\MetaTemplate\MetaTemplateRenderer;

final class MetaTemplateRendererTest extends TestCase
{
    /**
     * @param array<string, string> $values
     */
    #[Test]
    #[DataProvider('templateProvider')]
    public function itRendersATemplate(string $template, array $values, string $expected): void
    {
        self::assertSame($expected, (new MetaTemplateRenderer())->render($template, $values));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function templateProvider(): iterable
    {
        yield 'every variable filled' => [
            '%title% %brand%, en stock chez %store_name%',
            ['title' => 'Horatio', 'brand' => 'Dolce', 'store_name' => 'Ma boutique'],
            'Horatio Dolce, en stock chez Ma boutique',
        ];

        yield 'text without any variable' => [
            'Une boutique de vêtements',
            ['title' => 'Horatio'],
            'Une boutique de vêtements',
        ];

        yield 'empty variable leaves no double space before a comma' => [
            '%title% %brand%, en stock chez %store_name%',
            ['title' => 'Horatio', 'brand' => '', 'store_name' => 'Ma boutique'],
            'Horatio, en stock chez Ma boutique',
        ];

        yield 'empty variable between two dashes leaves one dash' => [
            '%title% - %brand% - %store_name%',
            ['title' => 'Horatio', 'brand' => '', 'store_name' => 'Ma boutique'],
            'Horatio - Ma boutique',
        ];

        yield 'empty variables leave no empty bracket nor trailing pipe' => [
            '%title% (%ref%) | %store_name%',
            ['title' => 'T-shirt', 'ref' => '', 'store_name' => ''],
            'T-shirt',
        ];

        yield 'empty variables leave the sentence its full stop only' => [
            '%title%, %brand%, %ref%.',
            ['title' => 'Horatio', 'brand' => '', 'ref' => ''],
            'Horatio.',
        ];

        yield 'unknown variable is removed' => [
            '%titel% %title%',
            ['title' => 'Horatio'],
            'Horatio',
        ];

        yield 'a variable named in the template but absent from the values is removed' => [
            '%title% %brand%',
            ['title' => 'Horatio'],
            'Horatio',
        ];

        yield 'the string zero is a value, not an empty one' => [
            '%title%',
            ['title' => '0'],
            '0',
        ];

        yield 'the string zero survives on both sides of a separator' => [
            '%title% - %brand%',
            ['title' => '0', 'brand' => '0'],
            '0 - 0',
        ];
    }

    #[Test]
    public function itCutsOnAWordAndLeavesNoEllipsisNorSeparatorBehind(): void
    {
        $title = 'Chemise en lin lavé coupe droite pour homme et pour femme';
        $values = ['title' => $title, 'store_name' => 'Ma boutique'];
        $renderer = new MetaTemplateRenderer();

        self::assertSame(
            'Chemise en lin lavé coupe droite pour homme et pour femme - Ma boutique',
            $renderer->render('%title% - %store_name%', $values),
        );

        $cut = $renderer->render('%title% - %store_name%', $values, 60);

        self::assertSame($title, $cut);
        self::assertLessThanOrEqual(60, mb_strlen($cut));
        self::assertStringEndsNotWith('…', $cut);
        self::assertStringEndsNotWith('...', $cut);
    }

    #[Test]
    public function itKeepsAResultShorterThanTheMaximumLengthWhole(): void
    {
        $rendered = (new MetaTemplateRenderer())->render(
            '%title% - %store_name%',
            ['title' => 'Horatio', 'store_name' => 'Ma boutique'],
            60,
        );

        self::assertSame('Horatio - Ma boutique', $rendered);
    }

    #[Test]
    public function itListsTheVariablesOfATemplateOnceEachInOrderOfAppearance(): void
    {
        self::assertSame(
            ['title', 'Brand', 'x1_y'],
            MetaTemplateRenderer::extractVariableNames('%title% %Brand% %title% %x1_y%'),
        );
    }

    #[Test]
    public function itListsNoVariableForATemplateWithoutAny(): void
    {
        self::assertSame([], MetaTemplateRenderer::extractVariableNames('Une boutique de vêtements'));
    }
}
