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

use Propel\Runtime\ActiveQuery\Criteria;
use SEOne\Model\Robots;
use SEOne\Model\RobotsQuery;
use SEOne\SEOne;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\LangTableMap;
use Thelia\Tools\URL;

class RobotTxtService
{
    private bool $isUniqueDomain;

    public function __construct()
    {
        $this->isUniqueDomain = (bool)ConfigQuery::read('one_domain_foreach_lang');
    }

    /**
     * Seed a default robots.txt for every domain that does not have one yet.
     * Existing rows are left untouched.
     */
    public function initializeDefaultRobots(): void
    {
        foreach ($this->getDomains() as $domainName) {
            if (RobotsQuery::create()->filterByDomainName($domainName)->findOne() !== null) {
                continue;
            }

            (new Robots())
                ->setDomainName($domainName)
                ->setRobotsContent(SEOne::getDefaultRobotsContent($domainName))
                ->save();
        }
    }

    public function saveRobotTxtByDomain(string $domainName, string $robotContent): void
    {
        $robot = RobotsQuery::create()->filterByDomainName($domainName)->findOne();

        if (!$robot) {
            $robot = new Robots();
            $robot->setDomainName($domainName);
        }

        $robot->setRobotsContent($robotContent)->save();
    }

    public function getDomains(): array
    {
        $domains = [];

        if (!$this->isUniqueDomain) {
            $baseUrl = $this->currentBaseUrl();

            if ('' !== $baseUrl) {
                $domains[] = $baseUrl;
            }

            return $domains;
        }

        $langs = LangQuery::create()
            ->select('domain')
            ->withColumn(LangTableMap::COL_URL, 'domain')
            ->filterByUrl(null, Criteria::ISNOTNULL)
            ->filterByActive(true)
            ->find();

        return $langs->getData();
    }

    /**
     * The request base url when there is one. A command (the activation run by template:set or
     * module:activate) has no request, and the URL service is not built: the configured shop url is
     * the domain then, in the format of the language urls (no trailing slash).
     */
    private function currentBaseUrl(): string
    {
        try {
            return URL::getInstance()->getBaseUrl();
        } catch (\RuntimeException) {
            return rtrim((string) ConfigQuery::getConfiguredShopUrl(), '/');
        }
    }

    public function getCurrentRobotTxt(string $domainName): ?Robots
    {
        return RobotsQuery::create()->filterByDomainName($domainName)->findOne();
    }
}
