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
use SEOne\Service\InstallSql;

final class InstallSqlTest extends TestCase
{
    #[Test]
    public function theInstallScriptNoLongerDropsATable(): void
    {
        $sql = InstallSql::keepingExistingTables((string) file_get_contents(__DIR__.'/../../../Config/TheliaMain.sql'));

        self::assertStringNotContainsString('DROP TABLE', $sql);
        self::assertSame(3, substr_count($sql, 'CREATE TABLE IF NOT EXISTS `'));
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `robots`', $sql);
    }

    #[Test]
    public function everythingElseOfTheScriptIsKept(): void
    {
        $sql = InstallSql::keepingExistingTables("SET FOREIGN_KEY_CHECKS = 0;\nDROP TABLE IF EXISTS `robots`;\n\nCREATE TABLE `robots`\n(\n    `id` INTEGER NOT NULL\n) ENGINE=InnoDB;\nSET FOREIGN_KEY_CHECKS = 1;\n");

        self::assertSame("SET FOREIGN_KEY_CHECKS = 0;\n\nCREATE TABLE IF NOT EXISTS `robots`\n(\n    `id` INTEGER NOT NULL\n) ENGINE=InnoDB;\nSET FOREIGN_KEY_CHECKS = 1;\n", $sql);
    }
}
