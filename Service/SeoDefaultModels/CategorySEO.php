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

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Util\PropelModelPager;
use SEOne\Model\Map\SeoneI18nTableMap;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;

readonly class CategorySEO implements SeoElementInterface
{
    use LocalizedValueTrait;
    use SeoneBreadcrumbTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
    }

    public function supports(string $view): bool
    {
        return $view === $this->getView();
    }

    public function getIdentifier(): string
    {
        return 'category_id';
    }

    public function getView(): string
    {
        return 'category';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        $microdata = null;
        if ($id) {
            $request = $this->requestStack->getCurrentRequest();
            $page = $params['page'] ?? $request?->get('page') ?? 1;
            $limit = $params['limit'] ?? $request?->get('limit') ?? SEOne::getConfigValue(SEOne::BETTER_SE0_LIMIT_CONFIG_KEY);

            $category = CategoryQuery::create()->filterById($id)->findOne();

            if (null !== $category) {
                $microdata = $this->getCategoryMicroData($category, $this->langService->getLang(), $page, $limit);
            }
        }

        return $this->getScriptsTag($microdata, $type, $id);
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            $breadcrumb = array_reverse($this->getCategoryPath($id));
        }

        return $breadcrumb;
    }

    public function getSeoPageTitle($id): string
    {
        $locale = $this->langService->getLocale();
        $category = CategoryQuery::create()->filterById($id)->findOne();
        $title = $this->firstLocalizedValue($category, ['getMetaTitle', 'getTitle'], $locale);

        return '' !== $title ? $title : (SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $locale) ?? '');
    }

    public function getSeoPageDesc($id): string
    {
        $locale = $this->langService->getLocale();
        $category = CategoryQuery::create()->filterById($id)->findOne();
        $description = $this->localizedValue($category, 'getMetaDescription', $locale);

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
        $category = CategoryQuery::create()->filterById($id)->findOne();
        $title = $this->localizedValue($category, 'getTitle', $locale);

        return '' !== $title ? $title : (ConfigQuery::read('store_name') ?? '');
    }

    private function getCategoryMicroData(Category $category, Lang $lang, $page, $limit)
    {
        $locale = $lang->getLocale();
        $category->setLocale($locale);

        $products = $this->getProduct($category, $page, $limit);

        // A freshly hydrated model answers getUrl() in its own default locale, not the
        // page's, so the lookups missed the rewritten urls and each item paid its own
        // query. Resolve them in the page's locale, in one batch where the core offers it.
        $productIds = [];
        foreach ($products as $product) {
            $productIds[] = $product->getId();
        }

        $url = URL::getInstance();
        if ([] !== $productIds && method_exists($url, 'preloadRewrittenUrls')) {
            $url->preloadRewrittenUrls('product', $locale, $productIds);
        }

        $itemListElement = [];

        $i = 1;
        foreach ($products as $product) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $i++,
                'url' => $product->getUrl($locale),
            ];
        }

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'ItemList',
            'url' => $category->getUrl($locale),
            'numberOfItems' => \count($products),
            'itemListElement' => $itemListElement,
        ];

        return $microData;
    }

    private function getProduct(Category $category, $page, $limit): PropelModelPager|ObjectCollection|array
    {
        if (null !== $limit) {
            return ProductQuery::create()->filterByCategory($category)->paginate($page, $limit);
        }

        return $category->getProducts();
    }

    public function getCategoryPath(int $categoryId, ?array $path = []): array
    {
        $locale = $this->langService->getLocale();
        $category = CategoryQuery::create()->filterById($categoryId)->findOne();

        if (null === $category) {
            return $path;
        }

        $path[] = [
            'url' => $category->setLocale($locale)->getUrl(),
            'title' => $this->localizedValue($category, 'getTitle', $locale),
        ];

        $parent = $category->getParent();

        if (null !== $parent && 0 !== $parent) {
            $path = $this->getCategoryPath($parent, $path);
        }

        return $path;
    }
}
