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

namespace SEOne;

use Propel\Runtime\Connection\ConnectionInterface;
use SEOne\Service\RobotTxtService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

class SEOne extends BaseModule
{
    public const string DOMAIN_NAME = 'seone';
    public const string BETTER_SE0_LIMIT_CONFIG_KEY = 'seone_limit';
    public const string SEO_CANONICAL_META_KEY = 'seo_canonical_meta';

    public function postActivation(?ConnectionInterface $con = null): void
    {
        if (!self::getConfigValue('is_initialized')) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);
            self::setConfigValue('is_initialized', 1);
        }

        // Seed a sensible robots.txt for every domain so the administrator does
        // not have to write one by hand (and no longer has to delete a stray
        // robots file from the front template).
        (new RobotTxtService())->initializeDefaultRobots();
    }

    /**
     * Default robots.txt content seeded on activation.
     *
     * The admin area is intentionally NOT disallowed here: with the
     * BackOfficePath module the admin lives behind an obfuscated prefix, so a
     * "Disallow: /admin" line is both useless and a path-disclosure risk — and
     * it is the very line BackOfficePath rewrites on every save, which made the
     * stored value grow by one prefix on each edit.
     */
    public static function getDefaultRobotsContent(string $domainName): string
    {
        return <<<ROBOTS
            # robots.txt
            User-agent: *
            Disallow: /cart
            Disallow: /404

            Sitemap: {$domainName}/sitemap
            ROBOTS;
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
