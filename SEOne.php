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
use SEOne\Service\InstallSql;
use SEOne\Service\MetaTemplate\MetaTemplateRepository;
use SEOne\Service\RobotTxtService;
use SEOne\Service\SeoRequestMemo;
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
            $installScript = tempnam(sys_get_temp_dir(), 'seone');

            if (false === $installScript) {
                throw new \RuntimeException('Unable to write the SEOne install script to the temporary directory');
            }

            file_put_contents($installScript, InstallSql::keepingExistingTables((string) file_get_contents(__DIR__.'/Config/TheliaMain.sql')));

            try {
                $database->insertSql(null, [$installScript]);
            } finally {
                unlink($installScript);
            }
            self::setConfigValue('is_initialized', 1);
        }

        // Seed a sensible robots.txt for every domain so the administrator does
        // not have to write one by hand (and no longer has to delete a stray
        // robots file from the front template).
        (new RobotTxtService())->initializeDefaultRobots();
    }

    /**
     * Up to 1.2.1 the meta template form stored its empty fields and default lengths, which
     * every page then read for nothing.
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        (new MetaTemplateRepository(new SeoRequestMemo()))->removeUnusedSettings($con);
        (new RobotTxtService())->replaceUntouchedLegacyDefaults($con);
    }

    /**
     * Default robots.txt content seeded on activation.
     *
     * The admin area is intentionally NOT disallowed here: with the
     * BackOfficePath module the admin lives behind an obfuscated prefix, so a
     * "Disallow: /admin" line is both useless and a path-disclosure risk — and
     * it is the very line BackOfficePath rewrites on every save, which made the
     * stored value grow by one prefix on each edit.
     *
     * The customer area, sign-in and checkout hold nothing a search engine can
     * use. The redirect and currency parameters are where crawlers get lost:
     * every page links to the sign-in form with its own address as the return
     * address, and to itself in every currency, so following those links never
     * ends (a crawler sent 51,000 such requests to the demo shop in one night).
     * The lang parameter stays open: the hreflang alternates point to it.
     */
    public static function getDefaultRobotsContent(string $domainName): string
    {
        return <<<ROBOTS
            # robots.txt
            User-agent: *
            Disallow: /account
            Disallow: /address
            Disallow: /checkout
            Disallow: /customer
            Disallow: /login
            Disallow: /register
            Disallow: /password
            Disallow: /order/track
            Disallow: /*?*redirect=
            Disallow: /*?*currency=

            Sitemap: {$domainName}/sitemap.xml
            ROBOTS;
    }

    /**
     * Default robots.txt content seeded up to 1.5.0, written for the Thelia 2 front: only
     * a domain still serving it word for word is moved to the current default.
     */
    public static function getLegacyDefaultRobotsContent(string $domainName): string
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
            // The tests are no services of the shop: a fixture resolver would give it a page kind.
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Tests/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
