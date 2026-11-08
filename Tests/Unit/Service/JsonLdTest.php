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

namespace SEOne\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SEOne\Service\JsonLd;

final class JsonLdTest extends TestCase
{
    #[Test]
    public function emptyDataPrintsNoScriptTag(): void
    {
        self::assertSame('', JsonLd::script([]));
    }

    #[Test]
    public function dataPrintsAScriptTag(): void
    {
        self::assertSame(
            '<script type="application/ld+json">{"@type":"Product"}</script>',
            JsonLd::script(['@type' => 'Product']),
        );
    }
}
