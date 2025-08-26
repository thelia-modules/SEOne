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
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Domain\Localization\LangService;

readonly class ContentSEO implements SeoElementInterface
{
    use SEOneMicroDataTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
        private FolderSEO $folderSeo,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
    }

    public function supports(string $view): bool
    {
        return $view === $this->getView();
    }

    public function getIdentifier(): string
    {
        return 'content_id';
    }

    public function getView(): string
    {
        return 'content';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        $microdata = null;
        if ($id) {
            $microdata = $this->getContentMicroData($id, $this->langService->getLang());
        }

        return $this->getScriptsTag($microdata, $type, $id);
    }

    public function getSeoPageTitle($id): string
    {
        $content = ContentQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $content?->getMetaTitle() ?? $content?->getTitle() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageDesc($id): string
    {
        $content = ContentQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $content?->getMetaDescription() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
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
        $content = ContentQuery::create()->filterById($id)->useI18nQuery($locale)->endUse()->findOne();

        return $content?->getTitle() ?? ConfigQuery::read('store_name') ?? '';
    }

    private function getContentMicroData($contentId, Lang $lang): ?array
    {
        $content = ContentQuery::create()->filterById($contentId)->findOne();

        if (null === $content) {
            return null;
        }

        $content->setLocale($lang->getLocale());

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Article',
            'url' => $content->getUrl(),
            'name' => $content->getTitle(),
            'abstract' => $content->getChapo(),
        ];

        $defaultFoIdlder = $content->getDefaultFolderId();

        if (null !== $defaultFoIdlder) {
            $default_folder = FolderQuery::create()->findOneById($defaultFoIdlder);
            if (null !== $default_folder) {
                $default_folder->setLocale($lang->getLocale());
                $microData['isPartOf'] = [
                    'name' => $default_folder->getTitle(),
                    'url' => $default_folder->getUrl(),
                ];
            }
        }

        return $microData;
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            $contentFolder = ContentFolderQuery::create()
                ->filterByContentId($id)
                ->findOne();

            $content = $contentFolder->getContent()->setlocale($this->langService->getLocale());

            $breadcrumb[] = [
                'url' => $content->getUrl(),
                'title' => $content->getTitle(),
            ];
            $breadcrumb = array_reverse($this->folderSeo->getFolderPath($contentFolder->getFolderId(), $breadcrumb));
        }

        return $breadcrumb;
    }
}
