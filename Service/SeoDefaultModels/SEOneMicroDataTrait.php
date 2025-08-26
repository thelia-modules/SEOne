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

namespace SEOne\Service\SeoDefaultModels;

use SEOne\Event\SEOneMicroDataEvent;
use SEOne\Event\SEOneMicroDataEvents;
use SEOne\Event\SEOneStoreMicroDataEvent;
use SEOne\Event\SEOneStoreMicroDataEvents;
use SEOne\Model\Map\SeoneI18nTableMap;
use SEOne\Model\SeoneQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Domain\Localization\LangService;

trait SEOneMicroDataTrait
{
    private readonly LangService $langService;
    private readonly EventDispatcherInterface $dispatcher;

    public function setDependencies(LangService $langService, EventDispatcherInterface $dispatcher): void
    {
        $this->langService = $langService;
        $this->dispatcher = $dispatcher;
    }

    private function getScriptsTag($microdata, ?string $defaultType, $objectId = null): string
    {
        $scriptsTag = '';

        $storeMicroData = $this->getStoreMicroData();
        $lang = $this->langService->getLang();

        if (!$lang) {
            $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        }
        if ($objectId) {
            $storeEvent = new SEOneStoreMicroDataEvent($storeMicroData, $defaultType,
                $objectId, $lang->getLocale());

            $this->dispatcher->dispatch(
                $storeEvent,
                SEOneStoreMicroDataEvents::BETTER_SEO_STORE_MICRO_DATA);

            $storeMicroData = $storeEvent->getStoreMicrodata();

            $viewEvent = new SEOneMicroDataEvent($microdata, $defaultType,
                $objectId, $lang->getLocale());

            $this->dispatcher->dispatch(
                $viewEvent,
                SEOneMicroDataEvents::BETTER_SEO_MICRO_DATA);
            $microdata = $viewEvent->getMicrodata();
        }

        $query = SeoneQuery::create()
            ->filterByObjectId($objectId)
            ->filterByObjectType($defaultType)
            ->useSEOneI18nQuery()
            ->filterByLocale($this->langService->getLocale())
            ->endUse()
            ->withColumn(SeoneI18nTableMap::COL_NOINDEX, 'noindex')
            ->withColumn(SeoneI18nTableMap::COL_NOFOLLOW, 'nofollow')
            ->withColumn(SeoneI18nTableMap::COL_H1, 'h1')
            ->withColumn(SeoneI18nTableMap::COL_JSON_DATA, 'json_data')
            ->findOne();

        if (null !== $query) {
            if ($query->getVirtualColumn('noindex') === 1 && $query->getVirtualColumn('nofollow') === 1) {
                $scriptsTag .= '<meta name="robots" content="noindex, nofollow">';
            } elseif ($query->getVirtualColumn('noindex') === 1) {
                $scriptsTag .= '<meta name="robots" content="noindex, follow">';
            } elseif ($query->getVirtualColumn('nofollow') === 1) {
                $scriptsTag .= '<meta name="robots" content="nofollow">';
            }
        }

        $scriptsTag .= '<script type="application/ld+json">'.json_encode($storeMicroData, \JSON_UNESCAPED_UNICODE).'</script>';
        if (null !== $microdata) {
            $scriptsTag .= '<script type="application/ld+json">'.json_encode($microdata, \JSON_UNESCAPED_UNICODE).'</script>';
        }

        if (null !== $query && $query->getVirtualColumn('json_data')) {
            $scriptsTag .= '<script type="application/ld+json">'.$query->getVirtualColumn('json_data').'</script>';
        }

        return $scriptsTag;
    }

    private function getStoreMicroData(): array
    {
        $country = CountryQuery::create()->filterById(ConfigQuery::read('store_country', 64))->findOne();
        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Organization',
            'name' => ConfigQuery::read('store_name'),
            'description' => SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()),
            'url' => ConfigQuery::read('url_site'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => ConfigQuery::read('store_address1').' '.ConfigQuery::read('store_address2').' '.ConfigQuery::read('store_address3'),
                'addressLocality' => ConfigQuery::read('store_city'),
                'addressCountry' => $country?->getIsoalpha2(),
                'postalCode' => ConfigQuery::read('store_zipcode'),
            ],
        ];

        return $microData;
    }
}
