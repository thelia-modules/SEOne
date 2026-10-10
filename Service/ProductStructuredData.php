<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SEOne\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\BrandI18nQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductImage;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Tools\URL;

/**
 * The schema.org pieces of a product that read several rows: its offers, one per visible declination,
 * and the short description of the products it is related or similar to.
 *
 * A fixed number of queries whatever the number of declinations or of products: the prices, the
 * attribute values, the tax rules, the brands and the pictures are each read once for the whole set;
 * the calculator of the shop (TaxCalculatorFactoryInterface) keeps its collection per tax rule and country.
 */
final readonly class ProductStructuredData
{
    public const string IN_STOCK = 'https://schema.org/InStock';

    public const string OUT_OF_STOCK = 'https://schema.org/OutOfStock';

    public const string NEW_CONDITION = 'https://schema.org/NewCondition';

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private TaxCalculatorFactoryInterface $taxCalculators,
    ) {
    }

    /**
     * One Offer per visible declination, in the merchant's order: its reference as sku, its GTIN,
     * its price in the currency browsed (promotion included, taxes of the country), its stock.
     * A declination without a price in the shop is left out: an offer without a price says nothing.
     * A shop that sells only some of them names the ids to offer (null: all the visible ones, empty: none).
     *
     * @param list<int>|null $declinationIds
     *
     * @return list<array<string, mixed>>
     */
    public function offers(Product $product, string $locale, Currency $currency, Country $country, ?array $declinationIds = null): array
    {
        $saleElements = $this->visibleSaleElements([(int) $product->getId()]);

        if (null !== $declinationIds) {
            $saleElements = array_values(array_filter($saleElements, static fn (ProductSaleElements $declination): bool => \in_array((int) $declination->getId(), $declinationIds, true)));
        }

        if ([] === $saleElements) {
            return [];
        }

        $prices = $this->taxedPrices($saleElements, [(int) $product->getId() => $product], $currency, $country);
        $values = $this->attributeValues($saleElements, $locale);
        $productTitle = trim((string) $product->setLocale($locale)->getTitle());
        $url = $product->getUrl($locale);
        $offers = [];

        foreach ($saleElements as $declination) {
            $price = $prices[(int) $declination->getId()] ?? null;

            if (null === $price) {
                continue;
            }

            $offer = [
                '@type' => 'Offer',
                'name' => trim($productTitle.' '.implode(' ', $values[(int) $declination->getId()] ?? [])),
                'sku' => (string) $declination->getRef(),
            ];
            $offer += self::gtin($declination->getEanCode());
            $offer += [
                'url' => $url,
                'priceCurrency' => (string) $currency->getCode(),
                'price' => self::amount($price),
                'itemCondition' => self::NEW_CONDITION,
                'availability' => $declination->getQuantity() > 0 ? self::IN_STOCK : self::OUT_OF_STOCK,
            ];

            $offers[] = $offer;
        }

        return $offers;
    }

    /**
     * A short Product node per visible product, in the order asked: name, address, reference, GTIN of
     * the default declination, first picture, brand and the offer of the default declination. A product
     * that is hidden or unknown is left out.
     *
     * @param list<int> $productIds
     *
     * @return list<array<string, mixed>>
     */
    public function summaries(array $productIds, string $locale, Currency $currency, Country $country): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(intval(...), $productIds), static fn (int $id): bool => $id > 0)));

        if ([] === $productIds) {
            return [];
        }

        $products = [];

        foreach (ProductQuery::create()->filterById($productIds, Criteria::IN)->filterByVisible(1)->find() as $product) {
            $products[(int) $product->getId()] = $product;
        }

        if ([] === $products) {
            return [];
        }

        $visibleIds = array_keys($products);
        $titles = $this->productTitles($visibleIds, $locale);
        $brands = $this->brandTitles($products, $locale);
        $images = $this->firstImages($visibleIds);
        $defaults = [];

        foreach ($this->visibleSaleElements($visibleIds, true) as $saleElements) {
            $defaults[(int) $saleElements->getProductId()] ??= $saleElements;
        }

        $prices = $this->taxedPrices(array_values($defaults), $products, $currency, $country);
        URL::getInstance()->preloadRewrittenUrls('product', $locale, $visibleIds);
        $summaries = [];

        foreach ($productIds as $productId) {
            $product = $products[$productId] ?? null;

            if (null === $product) {
                continue;
            }

            $summary = [
                '@type' => 'Product',
                'name' => $titles[$productId] ?? '',
                'url' => $product->getUrl($locale),
                'sku' => (string) $product->getRef(),
            ];

            $default = $defaults[$productId] ?? null;
            $summary += self::gtin($default?->getEanCode());

            if (isset($images[$productId])) {
                $summary['image'] = $this->imageUrl($images[$productId]);
            }

            if (isset($brands[$productId])) {
                $summary['brand'] = ['@type' => 'Brand', 'name' => $brands[$productId]];
            }

            $price = null === $default ? null : ($prices[(int) $default->getId()] ?? null);

            if (null !== $price) {
                $summary['offers'] = [
                    '@type' => 'Offer',
                    'priceCurrency' => (string) $currency->getCode(),
                    'price' => self::amount($price),
                    'availability' => $default->getQuantity() > 0 ? self::IN_STOCK : self::OUT_OF_STOCK,
                ];
            }

            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * The address of a picture of the catalogue, through the core's image processing (cache).
     */
    public function imageUrl(ProductImage $image): string
    {
        $library = ConfigQuery::read('images_library_path');
        $base = null === $library ? THELIA_LOCAL_DIR.'media'.DS.'images' : THELIA_ROOT.$library;

        $event = new ImageEvent();
        $event->setSourceFilepath($base.'/product/'.$image->getFile());
        $event->setCacheSubdirectory('product');

        try {
            $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

            return (string) $event->getFileUrl();
        } catch (\Exception) {
            return (string) $image->getFile();
        }
    }

    /**
     * The GTIN under the property schema.org names after its length (gtin8, gtin12, gtin13, gtin14),
     * `gtin` for any other code; nothing for an empty one.
     *
     * @return array<string, string>
     */
    public static function gtin(?string $code): array
    {
        $code = trim((string) $code);

        if ('' === $code) {
            return [];
        }

        $property = 1 === preg_match('/^\d{8}$|^\d{12,14}$/', $code) ? 'gtin'.\strlen($code) : 'gtin';

        return [$property => $code];
    }

    private static function amount(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * @param list<int> $productIds
     *
     * @return list<ProductSaleElements>
     */
    private function visibleSaleElements(array $productIds, bool $defaultFirst = false): array
    {
        $query = ProductSaleElementsQuery::create()
            ->filterByProductId($productIds, Criteria::IN)
            ->filterByVisible(true);

        if ($defaultFirst) {
            $query->orderByIsDefault(Criteria::DESC);
        }

        return iterator_to_array($query->orderByPosition()->orderById()->find(), false);
    }

    /**
     * The price a visitor pays for each declination, read in one query: the row of the currency
     * browsed, else the row of the default currency converted at the rates (the rule of
     * ProductSaleElements::getPricesByCurrency()); the promotion price when the declination is on
     * promotion; with the taxes of the product for the country.
     *
     * @param list<ProductSaleElements> $saleElements
     * @param array<int, Product>       $products     by id
     *
     * @return array<int, float> by declination id, declinations without a price left out
     */
    private function taxedPrices(array $saleElements, array $products, Currency $currency, Country $country): array
    {
        if ([] === $saleElements) {
            return [];
        }

        $defaultCurrency = Currency::getDefaultCurrency();
        $rows = [];

        foreach (ProductPriceQuery::create()
            ->filterByProductSaleElementsId(array_map(static fn (ProductSaleElements $pse): int => (int) $pse->getId(), $saleElements), Criteria::IN)
            ->filterByCurrencyId(array_unique([(int) $currency->getId(), (int) $defaultCurrency->getId()]), Criteria::IN)
            ->find() as $row) {
            $rows[(int) $row->getProductSaleElementsId()][(int) $row->getCurrencyId()] = $row;
        }

        // The tax rules of the products in one query: Product::getTaxRule() would read its own.
        $taxRules = [];

        foreach (TaxRuleQuery::create()->filterById(array_unique(array_map(static fn (Product $product): int => (int) $product->getTaxRuleId(), $products)), Criteria::IN)->find() as $taxRule) {
            $taxRules[(int) $taxRule->getId()] = $taxRule;
        }

        $prices = [];

        foreach ($saleElements as $pse) {
            $own = $rows[(int) $pse->getId()][(int) $currency->getId()] ?? null;
            $default = $rows[(int) $pse->getId()][(int) $defaultCurrency->getId()] ?? null;
            $product = $products[(int) $pse->getProductId()] ?? null;

            if (null === $product) {
                continue;
            }

            $untaxed = $this->untaxedPrice($pse, $own, $default, $currency, $defaultCurrency);

            if (null === $untaxed) {
                continue;
            }

            $taxRule = $taxRules[(int) $product->getTaxRuleId()] ?? null;

            if (null === $taxRule) {
                continue;
            }

            try {
                $prices[(int) $pse->getId()] = (float) $this->taxCalculators->createTaxCalculator()->loadTaxRule($taxRule, $country, $product)->getTaxedPrice($untaxed);
            } catch (TaxEngineException) {
                continue;
            }
        }

        return $prices;
    }

    private function untaxedPrice(ProductSaleElements $pse, ?ProductPrice $own, ?ProductPrice $default, Currency $currency, Currency $defaultCurrency): ?float
    {
        $promo = (bool) $pse->getPromo();

        if (null !== $own && !$own->getFromDefaultCurrency()) {
            return (float) ($promo ? $own->getPromoPrice() : $own->getPrice());
        }

        if (null === $default) {
            return null;
        }

        $amount = (float) ($promo ? $default->getPromoPrice() : $default->getPrice());

        return $amount * (float) $currency->getRate() / (float) $defaultCurrency->getRate();
    }

    /**
     * The titles of the attribute values each declination combines, in the language.
     *
     * @param list<ProductSaleElements> $saleElements
     *
     * @return array<int, list<string>> by declination id
     */
    private function attributeValues(array $saleElements, string $locale): array
    {
        $combinations = AttributeCombinationQuery::create()
            ->filterByProductSaleElementsId(array_map(static fn (ProductSaleElements $pse): int => (int) $pse->getId(), $saleElements), Criteria::IN)
            ->orderByAttributeId()
            ->find();

        $valueIds = [];

        foreach ($combinations as $combination) {
            $valueIds[] = (int) $combination->getAttributeAvId();
        }

        if ([] === $valueIds) {
            return [];
        }

        $titles = [];

        foreach (AttributeAvI18nQuery::create()->filterById(array_unique($valueIds), Criteria::IN)->filterByLocale($locale)->find() as $i18n) {
            $titles[(int) $i18n->getId()] = trim((string) $i18n->getTitle());
        }

        $values = [];

        foreach ($combinations as $combination) {
            $title = $titles[(int) $combination->getAttributeAvId()] ?? '';

            if ('' !== $title) {
                $values[(int) $combination->getProductSaleElementsId()][] = $title;
            }
        }

        return $values;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, string>
     */
    private function productTitles(array $productIds, string $locale): array
    {
        $titles = [];

        foreach (ProductI18nQuery::create()->filterById($productIds, Criteria::IN)->filterByLocale($locale)->find() as $i18n) {
            $titles[(int) $i18n->getId()] = trim((string) $i18n->getTitle());
        }

        return $titles;
    }

    /**
     * @param array<int, Product> $products
     *
     * @return array<int, string> brand title by product id
     */
    private function brandTitles(array $products, string $locale): array
    {
        $brandIds = array_filter(array_map(static fn (Product $product): int => (int) $product->getBrandId(), $products));

        if ([] === $brandIds) {
            return [];
        }

        $titles = [];

        foreach (BrandI18nQuery::create()->filterById(array_unique($brandIds), Criteria::IN)->filterByLocale($locale)->find() as $i18n) {
            $titles[(int) $i18n->getId()] = trim((string) $i18n->getTitle());
        }

        $byProduct = [];

        foreach ($brandIds as $productId => $brandId) {
            if ('' !== ($titles[$brandId] ?? '')) {
                $byProduct[$productId] = $titles[$brandId];
            }
        }

        return $byProduct;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, ProductImage> first visible picture by product id
     */
    private function firstImages(array $productIds): array
    {
        $images = [];

        foreach (ProductImageQuery::create()->filterByProductId($productIds, Criteria::IN)->filterByVisible(1)->orderByPosition()->find() as $image) {
            $images[(int) $image->getProductId()] ??= $image;
        }

        return $images;
    }
}
