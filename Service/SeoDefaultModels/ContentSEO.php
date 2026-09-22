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

use SEOne\Service\MetaTemplate\MetaTemplateField;
use SEOne\Service\MetaTemplate\MetaTemplateService;
use SEOne\Service\SeoRequestMemo;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;

readonly class ContentSEO implements SeoElementInterface
{
    use LocalizedValueTrait;
    use SEOneMicroDataTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
        SeoRequestMemo $seoRequestMemo,
        private FolderSEO $folderSeo,
        private MetaTemplateService $metaTemplates,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher, seoRequestMemo: $seoRequestMemo);
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
        $locale = $this->langService->getLocale();
        $content = ContentQuery::create()->findPk($id);
        $title = $this->localizedValue($content, 'getMetaTitle', $locale);

        if ('' === $title) {
            $title = $this->metaTemplates->render($this->getView(), MetaTemplateField::Title, (int) $id, $locale);
        }

        if ('' === $title) {
            $title = $this->localizedValue($content, 'getTitle', $locale);
        }

        // The page title falls back on the store name, never on the store description.
        return '' !== $title ? $title : ($this->seoConfigValue('title', ConfigQuery::read('store_name'), $locale) ?? '');
    }

    public function getSeoPageDesc($id): string
    {
        $locale = $this->langService->getLocale();
        $content = ContentQuery::create()->findPk($id);
        $description = $this->localizedValue($content, 'getMetaDescription', $locale);

        if ('' === $description) {
            $description = $this->metaTemplates->render($this->getView(), MetaTemplateField::Description, (int) $id, $locale);
        }

        return '' !== $description ? $description : ($this->seoConfigValue('description', ConfigQuery::read('store_description'), $locale) ?? '');
    }

    public function getSeoPageH1($id, string $type): string
    {
        $locale = $this->langService->getLocale();
        $query = $this->seoRow($type, $id, $locale);

        if (null !== $query && $query->getVirtualColumn('h1')) {
            return $query->getVirtualColumn('h1');
        }
        // Plain lookup, not useI18nQuery(): an inner join on the requested locale would
        // hide the content entirely instead of letting the default language answer.
        $content = ContentQuery::create()->findPk($id);
        $title = $this->localizedValue($content, 'getTitle', $locale);

        return '' !== $title ? $title : (ConfigQuery::read('store_name') ?? '');
    }

    private function getContentMicroData($contentId, Lang $lang): ?array
    {
        $content = ContentQuery::create()->findPk($contentId);

        if (null === $content) {
            return null;
        }

        $locale = $lang->getLocale();
        $content->setLocale($locale);

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Article',
            'url' => $content->getUrl(),
            'name' => $this->localizedValue($content, 'getTitle', $locale),
            'abstract' => $this->localizedValue($content, 'getChapo', $locale),
        ];

        $defaultFoIdlder = $content->getDefaultFolderId();

        if (null !== $defaultFoIdlder) {
            $default_folder = FolderQuery::create()->findPk($defaultFoIdlder);
            if (null !== $default_folder) {
                $default_folder->setLocale($locale);
                $microData['isPartOf'] = [
                    'name' => $this->localizedValue($default_folder, 'getTitle', $locale),
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

            $locale = $this->langService->getLocale();
            $content = $contentFolder?->getContent();

            if (null === $content) {
                return $breadcrumb;
            }

            $breadcrumb[] = [
                'url' => $content->setLocale($locale)->getUrl(),
                'title' => $this->localizedValue($content, 'getTitle', $locale),
            ];
            $breadcrumb = array_reverse($this->folderSeo->getFolderPath($contentFolder->getFolderId(), $breadcrumb));
        }

        return $breadcrumb;
    }
}
