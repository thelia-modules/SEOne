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

namespace SEOne\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use SEOne\Event\SEOneStoreMicroDataEvent;
use SEOne\Event\SEOneStoreMicroDataEvents;
use SEOne\Service\ProductStructuredData;
use SEOne\Service\SeoRequestMemo;
use SEOne\Service\SeoToolsService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The structured data of a product: one offer per visible declination, the products it is related or
 * similar to described in a fixed number of queries, the values of the catalogue unable to close the
 * script block they are printed in, and the store on a page without a model of its own.
 */
final class ProductStructuredDataTest extends IntegrationTestCase
{
    private const string LOCALE = 'en_US';

    private FixtureFactory $fixtures;

    private Category $category;

    private TaxRule $taxRule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
        $this->category = $this->fixtures->category();
        $this->taxRule = $this->fixtures->taxRule();
    }

    #[Test]
    public function everyVisibleDeclinationIsAnOfferWithItsOwnReferencePriceAndStock(): void
    {
        $product = $this->product('Gloves', 20.0);
        $size = $this->fixtures->attribute(['title' => 'Size']);
        $default = $this->defaultDeclination($product);
        $default->setRef('GLOVES-8')->setEanCode('3660815158807')->setQuantity(3)->save();
        $this->fixtures->attributeCombination($default, $this->fixtures->attributeAv($size, ['title' => '8']));

        $soldOut = $this->declination($product, 'GLOVES-9', 0, 30.0);
        $this->fixtures->attributeCombination($soldOut, $this->fixtures->attributeAv($size, ['title' => '9']));

        $hidden = $this->declination($product, 'GLOVES-10', 5, 20.0);
        $hidden->setVisible(false)->save();

        $offers = $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());

        self::assertSame(['GLOVES-8', 'GLOVES-9'], array_column($offers, 'sku'));
        self::assertSame(['Gloves 8', 'Gloves 9'], array_column($offers, 'name'));
        self::assertSame([ProductStructuredData::IN_STOCK, ProductStructuredData::OUT_OF_STOCK], array_column($offers, 'availability'));
        self::assertSame('3660815158807', $offers[0]['gtin13']);
        self::assertArrayNotHasKey('gtin13', $offers[1]);
        self::assertSame($this->taxed($product, 30.0), $offers[1]['price']);
        self::assertSame(Currency::getDefaultCurrency()->getCode(), $offers[0]['priceCurrency']);
    }

    #[Test]
    public function relatedProductsCostTheSameQueriesWhateverTheirNumber(): void
    {
        $brand = $this->fixtures->brand(['title' => 'Segura']);
        $products = [];

        for ($i = 1; $i <= 6; ++$i) {
            $products[] = $this->product('Jacket '.$i, 100.0 + $i, $brand->getId());
        }

        $ids = array_map(static fn (Product $product): int => (int) $product->getId(), $products);

        // The caches of the request (tax rules, rewritten addresses) warmed by another product first.
        $this->summaries([$ids[5]]);
        $one = $this->countQueries(fn () => $this->summaries([$ids[0]]));
        $six = $this->countQueries(fn () => $this->summaries($ids));

        self::assertSame($one, $six, 'one product or six, the same number of queries');

        $summaries = $this->summaries([$ids[2], $ids[0]]);
        self::assertSame(['Jacket 3', 'Jacket 1'], array_column($summaries, 'name'), 'the order asked');
        self::assertSame(['@type' => 'Brand', 'name' => 'Segura'], $summaries[0]['brand']);
        self::assertSame($this->taxed($products[2], 103.0), $summaries[0]['offers']['price']);
    }

    #[Test]
    public function aHiddenOrUnknownRelatedProductIsLeftOut(): void
    {
        $visible = $this->product('Visible', 10.0);
        $hidden = $this->product('Hidden', 10.0);
        $hidden->setVisible(0)->save();

        self::assertSame(['Visible'], array_column($this->summaries([(int) $hidden->getId(), 999999999, (int) $visible->getId()]), 'name'));
    }

    #[Test]
    public function aValueOfTheCatalogueCannotCloseTheScriptBlock(): void
    {
        $product = $this->product('Gloves </script><script>alert(1)</script>', 10.0);

        $html = $this->pageMicroData('product', (int) $product->getId());

        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertSame(substr_count($html, '<script'), substr_count($html, '</script>'), 'every block closed by its own tag');
        self::assertStringContainsString('"Gloves \\u003C/script\\u003E', $html, 'read back as the same characters');
    }

    #[Test]
    public function aPageWithoutAModelOfItsOwnCarriesTheStoreCompletedByItsListeners(): void
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $listener = static function (SEOneStoreMicroDataEvent $event): void {
            $event->setStoreMicrodata($event->getStoreMicrodata() + ['sameAs' => ['https://example.com/shop']]);
        };
        $dispatcher->addListener(SEOneStoreMicroDataEvents::BETTER_SEO_STORE_MICRO_DATA, $listener, -100);

        try {
            $html = $this->pageMicroData('index', null);
        } finally {
            $dispatcher->removeListener(SEOneStoreMicroDataEvents::BETTER_SEO_STORE_MICRO_DATA, $listener);
        }

        self::assertStringContainsString('"@type":"LocalBusiness"', $html);
        self::assertStringContainsString('"sameAs":["https://example.com/shop"]', $html);
    }

    private function product(string $title, float $price, ?int $brandId = null): Product
    {
        $product = $this->fixtures->product($this->category, $this->taxRule, Currency::getDefaultCurrency(), [
            'title' => $title,
            'locale' => self::LOCALE,
            'basePrice' => $price,
            'baseQuantity' => 5,
        ]);

        if (null !== $brandId) {
            $product->setBrandId($brandId)->save();
        }

        return $product;
    }

    private function defaultDeclination(Product $product): ProductSaleElements
    {
        $default = ProductSaleElementsQuery::create()->filterByProductId($product->getId())->filterByIsDefault(true)->findOne();
        self::assertNotNull($default);

        return $default;
    }

    private function declination(Product $product, string $ref, int $quantity, float $price): ProductSaleElements
    {
        $declination = $this->fixtures->productSaleElement($product, ['ref' => $ref, 'quantity' => $quantity]);
        $this->fixtures->productPrice($declination, Currency::getDefaultCurrency(), ['price' => (string) $price, 'promoPrice' => (string) $price]);

        return $declination;
    }

    private function taxed(Product $product, float $price): string
    {
        return number_format((float) $product->getTaxedPrice(Country::getDefaultCountry(), $price), 2, '.', '');
    }

    /**
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function summaries(array $ids): array
    {
        return $this->service()->summaries($ids, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());
    }

    private function countQueries(callable $read): int
    {
        $connection = Propel::getConnection('TheliaMain');
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $connection->useDebug(true);
        $before = $connection->getQueryCount();

        try {
            $read();
        } finally {
            $after = $connection->getQueryCount();
            $connection->useDebug(false);
        }

        return $after - $before;
    }

    private function pageMicroData(string $view, ?int $id): string
    {
        $this->getService(SeoRequestMemo::class)->reset();

        return $this->getService(SeoToolsService::class)->getSeoMicroData($view, $id);
    }

    private function service(): ProductStructuredData
    {
        return new ProductStructuredData($this->getService(EventDispatcherInterface::class), $this->getService(TaxCalculatorFactoryInterface::class));
    }
}
