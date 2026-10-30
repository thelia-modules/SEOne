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
use SEOne\SEOne;
use SEOne\Service\RobotTxtService;

final class RobotTxtDefaultTest extends TestCase
{
    private const string DOMAIN = 'https://shop.example';

    #[Test]
    public function theDefaultKeepsCrawlersOutOfTheCustomerAreaAndTheEndlessUrls(): void
    {
        $robots = SEOne::getDefaultRobotsContent(self::DOMAIN);

        foreach (['/account', '/checkout', '/customer', '/login', '/register', '/password', '/*?*redirect=', '/*?*currency='] as $path) {
            self::assertStringContainsString("Disallow: {$path}\n", $robots);
        }
        self::assertStringContainsString('Sitemap: https://shop.example/sitemap.xml', $robots);
    }

    #[Test]
    public function theDefaultLeavesTheLanguagesAndTheAdminAlone(): void
    {
        $robots = SEOne::getDefaultRobotsContent(self::DOMAIN);

        // The hreflang alternates point to ?lang=, and the admin path may be a secret one.
        self::assertStringNotContainsString('lang=', $robots);
        self::assertStringNotContainsString('/admin', $robots);
    }

    #[Test]
    public function onlyTheUntouchedLegacyDefaultIsReplaced(): void
    {
        $legacy = SEOne::getLegacyDefaultRobotsContent(self::DOMAIN);

        self::assertTrue(RobotTxtService::isLegacyDefault($legacy, self::DOMAIN));
        self::assertTrue(RobotTxtService::isLegacyDefault(str_replace("\n", "\r\n", $legacy)."\n", self::DOMAIN));
        self::assertFalse(RobotTxtService::isLegacyDefault($legacy."\nDisallow: /private", self::DOMAIN));
        self::assertFalse(RobotTxtService::isLegacyDefault($legacy, 'https://other.example'));
        self::assertFalse(RobotTxtService::isLegacyDefault(SEOne::getDefaultRobotsContent(self::DOMAIN), self::DOMAIN));
    }
}
