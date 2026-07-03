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
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Base\ProductCategoryQuery;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

readonly class ProductSEO implements SeoElementInterface
{
    use SEOneMicroDataTrait;
    use SmartyCompatibilityTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
        private TaxEngine $taxEngine,
        private CategorySEO $categorySEO,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
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
        $product = ProductQuery::create()->filterById($id)->findOne()->setlocale($locale);

        return $product?->getTitle() ?? ConfigQuery::read('store_name') ?? '';
    }

    public function getSeoPageTitle($id): string
    {
        $product = ProductQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $product?->getMetaTitle() ?? $product?->getTitle() ?? SEOne::getConfigValue('title', ConfigQuery::read('store_name'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageDesc($id): string
    {
        $product = ProductQuery::create()->filterById($id)->findOne()->setlocale($this->langService->getLocale());

        return $product?->getMetaDescription() ?? SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        $objectId = $params['id'] ?? $id;
        $product = ProductQuery::create()->filterById($objectId)->findOne();
        $relatedProducts = null;

        if (null !== $params && \array_key_exists('related_products', $params)) {
            $relatedProducts = \is_array($params['related_products']) ? $params['related_products'] : $this->explode($params['related_products']);
        }
        $microdata = $this->getProductMicroData(
            product: $product,
            lang: $this->langService->getLang(),
            relatedProducts: $relatedProducts
        );

        return $this->getScriptsTag(
            microdata: $microdata,
            defaultType: $type,
            objectId: $objectId
        );
    }

    private function getProductMicroData(Product $product, Lang $lang, $relatedProducts = []): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $product->setLocale($lang->getLocale());
        $image = ProductImageQuery::create()->filterByProductId($product->getId())->orderByPosition()->find()->getFirst();
        $pse = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(1)->findOne();
        $psePrice = ProductPriceQuery::create()->filterByProductSaleElementsId($pse->getId())->findOne();
        $taxCountry = $this->taxEngine->getDeliveryCountry();

        try {
            $taxedPrice = $product->getTaxedPrice(
                $taxCountry,
                $psePrice->getPrice()
            );
            if ($pse->getPromo()) {
                $taxedPrice = $product->getTaxedPromoPrice(
                    $taxCountry,
                    $psePrice->getPromoPrice()
                );
            }
        } catch (TaxEngineException $e) {
            $taxedPrice = null;
        }

        $imagePath = null;

        if ($image) {
            $baseSourceFilePath = ConfigQuery::read('images_library_path');
            if ($baseSourceFilePath === null) {
                $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
            } else {
                $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
            }
            $event = new ImageEvent();
            $sourceFilePath = $baseSourceFilePath.'/product/'.$image->getFile();

            $event->setSourceFilepath($sourceFilePath);
            $event->setCacheSubdirectory('product');

            try {
                $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);
                $imagePath = $event->getFileUrl();
            } catch (\Exception $e) {
                $imagePath = $image->getFile();
            }
        }

        $microData = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->getTitle(),
            'image' => $imagePath,
            'description' => $product->getDescription(),
            'sku' => $product->getRef(),
            'offers' => [
                'url' => $product->getUrl(),
                'priceCurrency' => $request->getSession()->getCurrency()->getCode(),
                'price' => $taxedPrice,
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => $pse->getQuantity() > 0 ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock',
            ],
        ];

        if ($pse->getEanCode()) {
            $microData['gtin13'] = $pse->getEanCode();
        }

        $brandTitle = null;

        if ($brand = $product->getBrand()) {
            $brandTitle = BrandI18nQuery::create()
                ->filterById($brand->getId())
                ->findOne()
                ->getTitle();
        }

        if ($brandTitle) {
            $microData['brand']['@type'] = 'Brand';
            $microData['brand']['name'] = $brandTitle;
        }

        if ($weight = $pse->getWeight()) {
            $microData['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $weight,
                'unitCode' => 'KGM',
                'unitText' => 'kg',
            ];
        }

        if ($relatedProducts) {
            foreach ($relatedProducts as $relatedProductId) {
                $relatedProduct = ProductQuery::create()->filterById($relatedProductId)->findOne();
                $microData['isRelatedTo'][] = $this->getProductMicroData(product: $relatedProduct, lang: $lang);
            }
        }

        return $microData;
    }

    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];

        if ($id) {
            $productCategory = ProductCategoryQuery::create()
                ->filterByProductId($id)
                ->findOne();

            $product = $productCategory->getProduct()->setlocale($this->langService->getLocale());

            $breadcrumb[] = [
                'url' => $product->getUrl(),
                'title' => $product->getTitle(),
            ];
            $breadcrumb = array_reverse($this->categorySEO->getCategoryPath($productCategory->getCategoryId(), $breadcrumb));
        }

        return $breadcrumb;
    }
}
