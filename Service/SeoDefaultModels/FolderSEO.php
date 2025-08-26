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

use SEOne\Model\Map\SeoneI18nTableMap;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Domain\Localization\LangService;

readonly class FolderSEO implements SeoElementInterface
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
        return 'folder_id';
    }

    public function getView(): string
    {
        return 'folder';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        if ($id) {
            $folder = FolderQuery::create()->filterById($id)->findOne();
            $microdata = $this->getFolderMicroData($folder, $this->langService->getLang());
        }

        return $this->getScriptsTag(microdata: $microdata, defaultType: $type, objectId: $id);
    }

    public function getSeoPageTitle($id): string
    {
        $folder = FolderQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $folder?->getMetaTitle() ?? $folder->getTitle() ?? SEOne::getConfigValue('title', ConfigQuery::read('store_name'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageDesc($id): string
    {
        $folder = FolderQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $folder?->getMetaDescription() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
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
        $folder = FolderQuery::create()->filterById($id)->useI18nQuery($locale)->endUse()->findOne();

        return $folder?->getTitle() ?? ConfigQuery::read('store_name') ?? '';
    }

    private function getFolderMicroData(Folder $folder, Lang $lang): array
    {
        $folder->setLocale($lang->getLocale());

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Guide',
            'url' => $folder->getUrl(),
            'name' => $folder->getTitle(),
            'abstract' => $folder->getChapo(),
        ];

        return $microData;
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            $breadcrumb = array_reverse($this->getFolderPath($id));
        }

        return $breadcrumb;
    }

    public function getFolderPath(int $fodlerId, ?array $path = []): array
    {
        $folder = FolderQuery::create()->filterById($fodlerId)->findOne()->setlocale($this->langService->getLocale());

        $path[] = [
            'url' => $folder->getUrl(),
            'title' => $folder->getTitle(),
        ];

        if ($folder->getParent() !== 0) {
            $path = $this->getFolderPath($folder->getParent(), $path);
        }

        return $path;
    }
}
