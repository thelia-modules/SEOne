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
use Thelia\Domain\Localization\LangService;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductQuery;

readonly class CategorySEO implements SeoElementInterface
{
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
            $microdata = $this->getCategoryMicroData($category, $this->langService->getLang(), $page, $limit);
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
        $category = CategoryQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $category?->getMetaTitle() ?? $category?->getTitle() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageDesc($id): string
    {
        $category = CategoryQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $category?->getMetaDescription() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
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
        $category = CategoryQuery::create()->filterById($id)->useI18nQuery($locale)->endUse()->findOne();

        return $category?->getTitle() ?? ConfigQuery::read('store_name') ?? '';
    }

    private function getCategoryMicroData(Category $category, Lang $lang, $page, $limit)
    {
        $category->setLocale($lang->getLocale());

        $products = $this->getProduct($category, $page, $limit);

        $itemListElement = [];

        $i = 1;
        foreach ($products as $product) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $i++,
                'url' => $product->getUrl(),
            ];
        }

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'ItemList',
            'url' => $category->getUrl(),
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
        $category = CategoryQuery::create()->filterById($categoryId)->findOne()->setlocale($this->langService->getLocale());

        $path[] = [
            'url' => $category->getUrl(),
            'title' => $category->getTitle(),
        ];

        if ($category->getParent() !== 0) {
            $path = $this->getCategoryPath($category->getParent(), $path);
        }

        return $path;
    }
}
