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

use Propel\Runtime\ActiveQuery\Criteria;
use SEOne\Service\MetaTemplate\MetaTemplateField;
use SEOne\Service\MetaTemplate\MetaTemplateService;
use SEOne\Service\ProductStructuredData;
use SEOne\Service\SeoRequestMemo;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Base\ProductCategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

readonly class ProductSEO implements SeoElementInterface
{
    use LocalizedValueTrait;
    use SEOneMicroDataTrait;
    use SmartyCompatibilityTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
        SeoRequestMemo $seoRequestMemo,
        private RequestStack $requestStack,
        private TaxEngine $taxEngine,
        private CategorySEO $categorySEO,
        private MetaTemplateService $metaTemplates,
        private ProductStructuredData $structuredData,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher, seoRequestMemo: $seoRequestMemo);
    }

    public function supports(string $view): bool
    {
        return $view === $this->getView();
    }

    public function getIdentifier(): string
    {
        return 'product_id';
    }

    public function getView(): string
    {
        return 'product';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoPageH1($id, string $type): string
    {
        $locale = $this->langService->getLocale();
        $query = $this->seoRow($type, $id, $locale);

        if (null !== $query && $query->getVirtualColumn('h1')) {
            return $query->getVirtualColumn('h1');
        }
        $product = ProductQuery::create()->findPk($id);
        $title = $this->localizedValue($product, 'getTitle', $locale);

        return '' !== $title ? $title : (ConfigQuery::read('store_name') ?? '');
    }

    public function getSeoPageTitle($id): string
    {
        $locale = $this->langService->getLocale();
        $product = ProductQuery::create()->findPk($id);
        $title = $this->localizedValue($product, 'getMetaTitle', $locale);

        if ('' === $title) {
            $title = $this->metaTemplates->render($this->getView(), MetaTemplateField::Title, (int) $id, $locale);
        }

        if ('' === $title) {
            $title = $this->localizedValue($product, 'getTitle', $locale);
        }

        return '' !== $title ? $title : ($this->seoConfigValue('title', ConfigQuery::read('store_name'), $locale) ?? '');
    }

    public function getSeoPageDesc($id): string
    {
        $locale = $this->langService->getLocale();
        $product = ProductQuery::create()->findPk($id);
        $description = $this->localizedValue($product, 'getMetaDescription', $locale);

        if ('' === $description) {
            $description = $this->metaTemplates->render($this->getView(), MetaTemplateField::Description, (int) $id, $locale);
        }

        return '' !== $description ? $description : ($this->seoConfigValue('description', ConfigQuery::read('store_description'), $locale) ?? '');
    }

    /**
     * The Product node of the page. Two parameters of the micro data event, which a listener of
     * `better.seo.page.micro.data` can set before this model runs: `related_products` (isRelatedTo)
     * and `similar_products` (isSimilarTo), product ids, an array or a comma separated list; and `offered_declinations`,
     * the ids of the declinations to offer (every visible one when absent, none when empty).
     */
    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        $objectId = $params['id'] ?? $id;
        $product = null === $objectId ? null : ProductQuery::create()->findPk($objectId);

        $microdata = null === $product ? null : $this->getProductMicroData(
            product: $product,
            lang: $this->langService->getLang(),
            relatedProducts: $this->productIds($params['related_products'] ?? null),
            similarProducts: $this->productIds($params['similar_products'] ?? null),
            offeredDeclinations: \array_key_exists('offered_declinations', $params) ? $this->productIds($params['offered_declinations']) : null,
        );

        return $this->getScriptsTag(
            microdata: $microdata,
            defaultType: $type,
            objectId: $objectId
        );
    }

    /**
     * @return list<int>
     */
    private function productIds(mixed $ids): array
    {
        if (null === $ids || '' === $ids) {
            return [];
        }

        return array_values(array_map(intval(...), \is_array($ids) ? $ids : $this->explode((string) $ids)));
    }

    /**
     * @param list<int> $relatedProducts
     * @param list<int> $similarProducts
     * @param list<int>|null $offeredDeclinations
     *
     * @return array<string, mixed>
     */
    private function getProductMicroData(Product $product, Lang $lang, array $relatedProducts = [], array $similarProducts = [], ?array $offeredDeclinations = null): array
    {
        $locale = $lang->getLocale();
        $product->setLocale($locale);
        $request = $this->requestStack->getCurrentRequest();
        $currency = null !== $request && $request->hasSession() ? $request->getSession()->getCurrency() : Currency::getDefaultCurrency();
        $country = $this->taxEngine->getDeliveryCountry();

        $image = ProductImageQuery::create()->filterByProductId($product->getId())->filterByVisible(1)->orderByPosition()->findOne();
        $default = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(1)->findOne();

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $this->localizedValue($product, 'getTitle', $locale),
            'image' => null === $image ? null : $this->structuredData->imageUrl($image),
            'description' => $this->localizedValue($product, 'getDescription', $locale),
            'sku' => $product->getRef(),
        ];

        $offers = $this->structuredData->offers($product, $locale, $currency, $country, $offeredDeclinations);

        if ([] !== $offers) {
            $microData['offers'] = $offers;
        }

        $microData += ProductStructuredData::gtin($default?->getEanCode());

        if (null !== ($brand = $product->getBrand())) {
            $brandTitle = $this->localizedValue($brand, 'getTitle', $locale);

            if ('' !== $brandTitle) {
                $microData['brand'] = ['@type' => 'Brand', 'name' => $brandTitle];
            }
        }

        if (null !== $default && ($weight = $default->getWeight())) {
            $microData['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $weight,
                'unitCode' => 'KGM',
                'unitText' => 'kg',
            ];
        }

        foreach (['isRelatedTo' => $relatedProducts, 'isSimilarTo' => $similarProducts] as $property => $productIds) {
            $summaries = $this->structuredData->summaries(
                array_values(array_diff($productIds, [(int) $product->getId()])),
                $locale,
                $currency,
                $country,
            );

            if ([] !== $summaries) {
                $microData[$property] = $summaries;
            }
        }

        return $microData;
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            // The path of the default category, as the product page names it: any other one
            // would depend on the order the rows were written in.
            $productCategory = ProductCategoryQuery::create()
                ->filterByProductId($id)
                ->orderByDefaultCategory(Criteria::DESC)
                ->findOne();

            $locale = $this->langService->getLocale();
            $product = $productCategory?->getProduct();

            if (null === $product) {
                return $breadcrumb;
            }

            $breadcrumb[] = [
                'url' => $product->setLocale($locale)->getUrl(),
                'title' => $this->localizedValue($product, 'getTitle', $locale),
            ];
            $breadcrumb = array_reverse($this->categorySEO->getCategoryPath($productCategory->getCategoryId(), $breadcrumb));
        }

        return $breadcrumb;
    }
}
