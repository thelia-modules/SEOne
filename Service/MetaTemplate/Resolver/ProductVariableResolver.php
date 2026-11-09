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

namespace SEOne\Service\MetaTemplate\Resolver;

use SEOne\Service\MetaTemplate\HtmlToPlainText;
use SEOne\Service\MetaTemplate\PreviewRecordsInterface;
use SEOne\Service\MetaTemplate\VariableResolverInterface;
use SEOne\Service\SeoDefaultModels\LocalizedValueTrait;
use SEOne\Service\VisitorPrices;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Taxation\TaxEngine\Exception\TaxEngineException;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Variables of a product page. Prices are those of the default sale element, in the currency the
 * visitor browses in, formatted the way the rest of the shop formats money. They are what the visitor
 * pays when the core resolves it (price list, group), and empty when the visitor is not shown the
 * price of the product.
 */
final readonly class ProductVariableResolver implements VariableResolverInterface, PreviewRecordsInterface
{
    use LocalizedValueTrait;

    public function __construct(
        private RequestStack $requestStack,
        private TaxEngine $taxEngine,
        private FormatService $formatService,
        private VisitorPrices $visitorPrices = new VisitorPrices(),
    ) {
    }

    public function getView(): string
    {
        return 'product';
    }

    public function getVariableNames(): array
    {
        return [
            'title',
            'description',
            'chapo',
            'ref',
            'brand',
            'category_title',
            'taxed_price',
            'untaxed_price',
        ];
    }

    public function resolve(int $id, string $locale): array
    {
        if ($id <= 0) {
            return [];
        }

        $product = ProductQuery::create()->findPk($id);

        if (null === $product) {
            return [];
        }

        [$taxedPrice, $untaxedPrice] = $this->prices($product, $locale);

        return [
            'title' => $this->localizedValue($product, 'getTitle', $locale),
            'description' => HtmlToPlainText::convert($this->localizedValue($product, 'getDescription', $locale)),
            'chapo' => HtmlToPlainText::convert($this->localizedValue($product, 'getChapo', $locale)),
            'ref' => (string) $product->getRef(),
            'brand' => $this->brandTitle($product, $locale),
            'category_title' => $this->defaultCategoryTitle($product, $locale),
            'taxed_price' => $taxedPrice,
            'untaxed_price' => $untaxedPrice,
        ];
    }

    public function listPreviewRecords(string $locale, int $limit): array
    {
        $records = [];

        foreach (ProductQuery::create()->joinWithI18n($locale)->orderById()->limit($limit)->find() as $product) {
            $title = (string) $product->setLocale($locale)->getTitle();
            $records[$product->getId()] = '' === $title ? (string) $product->getRef() : $title.' ('.$product->getRef().')';
        }

        return $records;
    }

    private function brandTitle(Product $product, string $locale): string
    {
        $brand = $product->getBrand();

        return null === $brand ? '' : $this->localizedValue($brand, 'getTitle', $locale);
    }

    private function defaultCategoryTitle(Product $product, string $locale): string
    {
        $categoryId = $product->getDefaultCategoryId();

        if ($categoryId <= 0) {
            return '';
        }

        return $this->localizedValue(CategoryQuery::create()->findPk($categoryId), 'getTitle', $locale);
    }

    /**
     * @return array{string, string} the taxed price then the untaxed one, both formatted, both '' when the product has no price
     */
    private function prices(Product $product, string $locale): array
    {
        if ([] !== $this->visitorPrices->hiddenAmong([(int) $product->getId()])) {
            return ['', ''];
        }

        $productSaleElements = ProductSaleElementsQuery::create()
            ->filterByProductId($product->getId())
            ->filterByIsDefault(true)
            ->findOne();

        if (null === $productSaleElements) {
            return ['', ''];
        }

        $currency = $this->currency();

        if (null === $currency) {
            return ['', ''];
        }

        // The reading of the front: the row typed in the browsing currency when there is one,
        // else the default-currency price converted at the rate of the browsing currency.
        try {
            $prices = $productSaleElements->getPricesByCurrency($currency);
        } catch (\RuntimeException) {
            return ['', ''];
        }

        $amount = $this->visitorPrices->untaxedPrices([(int) $productSaleElements->getId()], $currency)[(int) $productSaleElements->getId()]
            ?? ((bool) $productSaleElements->getPromo() ? $prices->getPromoPrice() : $prices->getPrice());

        return [
            $this->taxedPrice($product, $amount, $currency, $locale),
            $this->formatService->money($amount, $currency->getId(), $locale),
        ];
    }

    private function taxedPrice(Product $product, float $amount, Currency $currency, string $locale): string
    {
        // TaxEngine::getDeliveryCountry() reads the main request's session without a null check
        // (core TaxEngine.php:50): outside an HTTP request — a console command rendering a
        // sitemap, a worker — it fatals rather than raising a TaxEngineException. No session,
        // no delivery country, so no taxed price either.
        $mainRequest = $this->requestStack->getMainRequest();

        if (null === $mainRequest || !$mainRequest->hasSession()) {
            return '';
        }

        try {
            $taxedAmount = $product->getTaxedPrice($this->taxEngine->getDeliveryCountry(), $amount);
        } catch (TaxEngineException) {
            return '';
        }

        return $this->formatService->money($taxedAmount, $currency->getId(), $locale);
    }

    private function currency(): ?Currency
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request && $request->hasSession()) {
            $session = $request->getSession();

            if ($session instanceof Session) {
                return $session->getCurrency();
            }
        }

        return CurrencyQuery::create()->findOneByByDefault(1);
    }
}
