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
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Domain\Localization\LangService;

readonly class PageSEO implements SeoElementInterface
{
    use SEOneMicroDataTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
    }

    public function supports(string $view): bool
    {
        return $view === $this->getView();
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
        if ($id) {
            $page = PageQuery::create()->filterById($id)->findOne();
            $microdata = $this->getPageMicroData($page, $this->langService->getLang());
        }

        return $this->getScriptsTag(microdata: $microdata, defaultType: $type, objectId: $id);
    }

    public function getSeoPageTitle($id): string
    {
        $page = PageQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $page?->getMetaTitle() ?? $page->getTitle() ?? SEOne::getConfigValue('title', ConfigQuery::read('store_name'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageDesc($id): string
    {
        $page = PageQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $page?->getMetaDescription() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
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
        $page = PageQuery::create()->filterById($id)->useI18nQuery($locale)->endUse()->findOne();

        return $page?->getTitle() ?? ConfigQuery::read('store_name') ?? '';
    }

    private function getPageMicroData(Page $page, Lang $lang): array
    {
        $page->setLocale($lang->getLocale());

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Guide',
            'url' => $page->getUrl(),
            'name' => $page->getTitle(),
            'abstract' => $page->getChapo(),
        ];

        return $microData;
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            $page = PageQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

            $breadcrumb[] = [
                'url' => $page->getUrl(),
                'title' => $page->getTitle(),
            ];
        }

        return $breadcrumb;
    }
}
