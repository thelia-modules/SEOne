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
use SEOne\Model\Seone as SeoneModel;
use SEOne\SEOne;
use SEOne\Service\SeoRequestMemo;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Domain\Localization\Service\LangService;

trait SEOneMicroDataTrait
{
    private readonly LangService $langService;
    private readonly EventDispatcherInterface $dispatcher;
    private readonly ?SeoRequestMemo $seoRequestMemo;

    public function setDependencies(LangService $langService, EventDispatcherInterface $dispatcher, ?SeoRequestMemo $seoRequestMemo = null): void
    {
        $this->langService = $langService;
        $this->dispatcher = $dispatcher;
        $this->seoRequestMemo = $seoRequestMemo;
    }

    /**
     * The SEO row of the object, read once per request. Without the memo — a caller that
     * built the service by hand — the read happens as before.
     */
    private function seoRow(?string $objectType, $objectId, string $locale): ?SeoneModel
    {
        if (null === $this->seoRequestMemo) {
            return (new SeoRequestMemo())->getRow($objectType, $objectId, $locale);
        }

        return $this->seoRequestMemo->getRow($objectType, $objectId, $locale);
    }

    /**
     * A module setting, read once per request (see {@see SeoRequestMemo::getConfigValue()}).
     */
    private function seoConfigValue(string $name, ?string $default = null, ?string $locale = null): ?string
    {
        if (null === $this->seoRequestMemo) {
            return SEOne::getConfigValue($name, $default, $locale);
        }

        return $this->seoRequestMemo->getConfigValue($name, $default, $locale);
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

            // No microdata means the SEO target does not exist: the event requires an array, and
            // there is nothing for listeners to enrich.
            if (null !== $microdata) {
                $viewEvent = new SEOneMicroDataEvent($microdata, $defaultType,
                    $objectId, $lang->getLocale());

                $this->dispatcher->dispatch(
                    $viewEvent,
                    SEOneMicroDataEvents::BETTER_SEO_MICRO_DATA);
                $microdata = $viewEvent->getMicrodata();
            }
        }

        $query = $this->seoRow($defaultType, $objectId, $this->langService->getLocale());

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
        $country = CountryQuery::create()->findPk(ConfigQuery::read('store_country', 64));
        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Organization',
            'name' => ConfigQuery::read('store_name'),
            'description' => $this->seoConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()),
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
