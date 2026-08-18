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
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;

readonly class FolderSEO implements SeoElementInterface
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
        $microdata = null;

        if ($id) {
            $folder = FolderQuery::create()->filterById($id)->findOne();

            if (null !== $folder) {
                $microdata = $this->getFolderMicroData($folder, $this->langService->getLang());
            }
        }

        return $this->getScriptsTag(microdata: $microdata, defaultType: $type, objectId: $id);
    }

    public function getSeoPageTitle($id): string
    {
        $locale = $this->langService->getLocale();
        $folder = FolderQuery::create()->filterById($id)->findOne();
        $title = $this->firstLocalizedValue($folder, ['getMetaTitle', 'getTitle'], $locale);

        return '' !== $title ? $title : (SEOne::getConfigValue('title', ConfigQuery::read('store_name'), $locale) ?? '');
    }

    public function getSeoPageDesc($id): string
    {
        $locale = $this->langService->getLocale();
        $folder = FolderQuery::create()->filterById($id)->findOne();
        $description = $this->localizedValue($folder, 'getMetaDescription', $locale);

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
        // hide the folder entirely instead of letting the default language answer.
        $folder = FolderQuery::create()->filterById($id)->findOne();
        $title = $this->localizedValue($folder, 'getTitle', $locale);

        return '' !== $title ? $title : (ConfigQuery::read('store_name') ?? '');
    }

    private function getFolderMicroData(Folder $folder, Lang $lang): array
    {
        $locale = $lang->getLocale();
        $folder->setLocale($locale);

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Guide',
            'url' => $folder->getUrl(),
            'name' => $this->localizedValue($folder, 'getTitle', $locale),
            'abstract' => $this->localizedValue($folder, 'getChapo', $locale),
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
        $locale = $this->langService->getLocale();
        $folder = FolderQuery::create()->filterById($fodlerId)->findOne();

        if (null === $folder) {
            return $path;
        }

        $path[] = [
            'url' => $folder->setLocale($locale)->getUrl(),
            'title' => $this->localizedValue($folder, 'getTitle', $locale),
        ];

        $parent = $folder->getParent();

        if (null !== $parent && 0 !== $parent) {
            $path = $this->getFolderPath($parent, $path);
        }

        return $path;
    }
}
