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

use Page\Model\Base\PageQuery;
use Page\Model\Page;
use SEOne\Model\Map\SeoneI18nTableMap;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

readonly class PageSEO implements SeoElementInterface
{
    use LocalizedValueTrait;
    use SEOneMicroDataTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
    }

    public function supports(string $view): bool
    {
        // The Page module is not a dependency of SEOne: without it, every query
        // below is a fatal error, so hand the view over to the default SEO service.
        return $view === $this->getView() && class_exists(PageQuery::class);
    }

    public function getIdentifier(): string
    {
        return 'page_id';
    }

    public function getView(): string
    {
        return 'page';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        $microdata = null;

        if ($id) {
            $page = PageQuery::create()->filterById($id)->findOne();

            if (null !== $page) {
                $microdata = $this->getPageMicroData($page, $this->langService->getLang());
            }
        }

        return $this->getScriptsTag(microdata: $microdata, defaultType: $type, objectId: $id);
    }

    public function getSeoPageTitle($id): string
    {
        $locale = $this->langService->getLocale();
        $page = PageQuery::create()->filterById($id)->findOne();
        $title = $this->firstLocalizedValue($page, ['getMetaTitle', 'getTitle'], $locale);

        return '' !== $title ? $title : (SEOne::getConfigValue('title', ConfigQuery::read('store_name'), $locale) ?? '');
    }

    public function getSeoPageDesc($id): string
    {
        $locale = $this->langService->getLocale();
        $page = PageQuery::create()->filterById($id)->findOne();
        $description = $this->localizedValue($page, 'getMetaDescription', $locale);

        return '' !== $description ? $description : (SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $locale) ?? '');
    }

    public function getSeoPageH1($id, string $type): string
    {
        $locale = $this->langService->getLocale();
        $query = SeoneQuery::create()
            ->filterByObjectId($id)
            ->filterByObjectType($type)
            ->useSEOneI18nQuery()
            ->filterByLocale($locale)
            ->endUse()
            ->withColumn(SeoneI18nTableMap::COL_H1, 'h1')
            ->findOne();

        if (null !== $query && $query->getVirtualColumn('h1')) {
            return $query->getVirtualColumn('h1');
        }
        // Plain lookup, not useI18nQuery(): an inner join on the requested locale would
        // hide the page entirely instead of letting the default language answer.
        $page = PageQuery::create()->filterById($id)->findOne();
        $title = $this->localizedValue($page, 'getTitle', $locale);

        return '' !== $title ? $title : (ConfigQuery::read('store_name') ?? '');
    }

    private function getPageMicroData(Page $page, Lang $lang): array
    {
        $locale = $lang->getLocale();
        $page->setLocale($locale);

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Guide',
            'url' => $page->getUrl(),
            'name' => $this->localizedValue($page, 'getTitle', $locale),
            'abstract' => $this->localizedValue($page, 'getChapo', $locale),
        ];

        return $microData;
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            $locale = $this->langService->getLocale();
            $page = PageQuery::create()->filterById($id)->findOne();

            if (null !== $page) {
                $breadcrumb[] = [
                    'url' => $page->setLocale($locale)->getUrl(),
                    'title' => $this->localizedValue($page, 'getTitle', $locale),
                ];
            }
        }

        return $breadcrumb;
    }
}
