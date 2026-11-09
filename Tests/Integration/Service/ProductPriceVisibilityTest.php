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
use SEOne\Service\MetaTemplate\MetaTemplateService;
use SEOne\Service\ProductStructuredData;
use SEOne\Service\SeoRequestMemo;
use SEOne\Service\SeoToolsService;
use SEOne\Service\VisitorPrices;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\PriceList;
use Thelia\Model\PriceListAssignment;
use Thelia\Model\PriceListItem;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsCustomer;

/**
 * The structured data and the meta variables of a product tell the price the visitor is shown, no
 * other: no amount where the shop or the product shows its prices to identified customers only or on
 * request, the price of the price list of an identified customer where they have one, and the
 * catalogue price, as it always was, where nothing prices the visitor apart.
 *
 * Catalogue price 100.00 untaxed, price list 80.00: the amount of the catalogue must not be readable
 * behind a price that is hidden, nor behind a price list.
 */
final class ProductPriceVisibilityTest extends IntegrationTestCase
{
    use LogsInAsCustomer;

    private const string LOCALE = 'en_US';

    private const float CATALOGUE_PRICE = 100.0;

    private const float PRICE_LIST_PRICE = 80.0;

    private FixtureFactory $fixtures;

    private Category $category;

    private TaxRule $taxRule;

    protected function setUp(): void
    {
        if (!class_exists(PriceVisibilityResolver::class)) {
            self::markTestSkipped('The core of the shop has no price display mode.');
        }

        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
        $this->category = $this->fixtures->category();
        $this->taxRule = $this->fixtures->taxRule();
        $this->forgetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->logoutCustomer();
        $this->forgetPriceMemos();

        parent::tearDown();
    }

    #[Test]
    public function aVisitorOfAShopThatShowsItsPricesToCustomersIsToldNoAmountOfTheProduct(): void
    {
        $product = $this->product('Gloves');
        $this->shopShows('logged_in');

        self::assertSame([], $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()));
        $this->assertNoAmount($this->productNode($product));
    }

    #[Test]
    public function aVisitorIsToldNoAmountOfTheProductsOfAListOfRelatedProducts(): void
    {
        $shown = $this->product('Shown');
        $hidden = $this->product('Hidden');
        $this->productShows($hidden, 'logged_in');

        $summaries = $this->summaries([(int) $hidden->getId(), (int) $shown->getId()]);

        self::assertSame(['Hidden', 'Shown'], array_column($summaries, 'name'), 'the product is listed, with no price');
        self::assertArrayNotHasKey('offers', $summaries[0]);
        self::assertSame($this->taxed($shown, self::CATALOGUE_PRICE), $summaries[1]['offers']['price'], 'the other products keep their price');
        $this->assertNoAmount([$summaries[0]]);
    }

    #[Test]
    public function aVisitorOfAShopThatShowsItsPricesToCustomersIsToldNoAmountOfAListOfProducts(): void
    {
        $products = [$this->product('One'), $this->product('Two')];
        $this->shopShows('logged_in');

        $summaries = $this->summaries(array_map(static fn (Product $product): int => (int) $product->getId(), $products));

        self::assertCount(2, $summaries);
        $this->assertNoAmount($summaries);
    }

    #[Test]
    public function anIdentifiedCustomerOfAShopThatShowsItsPricesToCustomersIsToldThePriceTheyPay(): void
    {
        $product = $this->product('Gloves');
        $customer = $this->customerOfAPriceList($product);
        $this->shopShows('logged_in');
        $this->loginAsCustomerInSession($customer);
        $this->forgetPriceMemos();

        $offers = $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());

        self::assertSame([$this->taxed($product, self::PRICE_LIST_PRICE)], array_column($offers, 'price'), 'the price of the list of the customer, not the one of the catalogue');
        self::assertSame([$this->taxed($product, self::PRICE_LIST_PRICE)], array_column($this->productNode($product)['offers'], 'price'), 'the page says the same');
        self::assertSame($this->taxed($product, self::PRICE_LIST_PRICE), $this->summaries([(int) $product->getId()])[0]['offers']['price'], 'so does a list of products');
    }

    #[Test]
    public function aCustomerWhoseGroupHasNoPriceListIsToldTheCataloguePrice(): void
    {
        $product = $this->product('Gloves');
        $this->shopShows('logged_in');
        $this->loginAsCustomerInSession($this->fixtures->customer($this->fixtures->customerTitle()));
        $this->forgetPriceMemos();

        $offers = $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());

        self::assertSame([$this->taxed($product, self::CATALOGUE_PRICE)], array_column($offers, 'price'));
    }

    #[Test]
    public function aPromotionIsToldToACustomerOfAPriceListThatDoesNotPriceTheProduct(): void
    {
        $product = $this->product('Gloves');
        $listed = $this->product('Helmet');
        $product->getDefaultSaleElements()->getProductPrices()->getFirst()->setPromoPrice('70.000000')->save($this->getPropelConnection());
        $product->getDefaultSaleElements()->setPromo(1)->save($this->getPropelConnection());
        $this->loginAsCustomerInSession($this->customerOfAPriceList($listed));
        $this->forgetPriceMemos();

        $offers = $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());

        self::assertSame([$this->taxed($product, 70.0)], array_column($offers, 'price'), 'the price charged, the promotion included');
    }

    #[Test]
    public function aVisitorIsToldTheCataloguePriceAndNotThePriceOfAListOfCustomers(): void
    {
        $product = $this->product('Gloves');
        $this->customerOfAPriceList($product);
        $this->forgetPriceMemos();

        $offers = $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());

        self::assertSame([$this->taxed($product, self::CATALOGUE_PRICE)], array_column($offers, 'price'));
    }

    #[Test]
    public function aProductOnRequestIsToldToNobodyWithAnAmount(): void
    {
        $product = $this->product('Gloves');
        $customer = $this->customerOfAPriceList($product);
        $this->productShows($product, 'on_request');

        self::assertSame([], $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()), 'a visitor');
        $this->assertNoAmount($this->productNode($product));

        $this->loginAsCustomerInSession($customer);
        $this->forgetPriceMemos();

        self::assertSame([], $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()), 'a customer');
        $this->assertNoAmount($this->productNode($product));
        self::assertArrayNotHasKey('offers', $this->summaries([(int) $product->getId()])[0]);
    }

    #[Test]
    public function aProductThatShowsItsPriceInAShopThatHidesThemIsToldWithItsAmount(): void
    {
        $product = $this->product('Gloves');
        $this->shopShows('on_request');
        $this->productShows($product, 'visible');

        $offers = $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry());

        self::assertSame([$this->taxed($product, self::CATALOGUE_PRICE)], array_column($offers, 'price'));
    }

    #[Test]
    public function aShopThatShowsEveryPriceTellsTheSameAsACoreThatKnowsNoPriceDisplayMode(): void
    {
        $product = $this->product('Gloves', 2);
        $other = $this->product('Helmet');
        $ids = [(int) $product->getId(), (int) $other->getId()];
        $legacy = new ProductStructuredData($this->getService(EventDispatcherInterface::class), $this->getService(TaxCalculatorFactoryInterface::class), new VisitorPrices());

        self::assertSame(
            $legacy->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()),
            $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()),
        );
        self::assertSame(
            $legacy->summaries($ids, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()),
            $this->summaries($ids),
        );
        self::assertCount(2, $this->service()->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()));
    }

    #[Test]
    public function relatedProductsCostTheSameQueriesWhateverTheirNumberWhateverTheyShow(): void
    {
        $products = [];

        for ($i = 1; $i <= 7; ++$i) {
            $products[] = $this->product('Jacket '.$i);
        }

        $this->productShows($products[1], 'on_request');
        $customer = $this->customerOfAPriceList(...$products);
        $ids = array_map(static fn (Product $product): int => (int) $product->getId(), $products);
        $this->shopShows('logged_in');

        foreach ([null, $customer] as $visitor) {
            $this->logoutCustomer();

            if (null !== $visitor) {
                $this->loginAsCustomerInSession($visitor);
            }

            $this->forgetPriceMemos();
            // What the request memorizes (tax rules, rewritten addresses, price lists) is warmed by a product that is in neither list.
            $this->summaries([$ids[6]]);
            $one = $this->countQueries(fn () => $this->summaries([$ids[0]]));
            $five = $this->countQueries(fn () => $this->summaries(\array_slice($ids, 1, 5)));

            self::assertSame($one, $five, null === $visitor ? 'a visitor' : 'a customer');
        }
    }

    #[Test]
    public function theVariablesOfAMetaTemplateTellNoPriceToAVisitorWhoIsShownNone(): void
    {
        $product = $this->product('Gloves');
        $this->shopShows('logged_in');

        $values = $this->variableValues($product);

        self::assertSame('', $values['taxed_price']);
        self::assertSame('', $values['untaxed_price']);
        self::assertSame('Gloves', $values['title']);
    }

    #[Test]
    public function theVariablesOfAMetaTemplateTellTheCustomerThePriceTheyPay(): void
    {
        $product = $this->product('Gloves');
        $customer = $this->customerOfAPriceList($product);
        $this->shopShows('logged_in');
        $this->loginAsCustomerInSession($customer);
        $this->forgetPriceMemos();

        $values = $this->variableValues($product);

        self::assertSame($this->money(self::PRICE_LIST_PRICE), $values['untaxed_price']);
        self::assertSame($this->money((float) $product->getTaxedPrice(Country::getDefaultCountry(), self::PRICE_LIST_PRICE)), $values['taxed_price']);
    }

    #[Test]
    public function theVariablesOfAMetaTemplateTellTheCataloguePriceWhereEveryPriceIsShown(): void
    {
        $product = $this->product('Gloves');

        $values = $this->variableValues($product);

        self::assertSame($this->money(self::CATALOGUE_PRICE), $values['untaxed_price']);
    }

    private function product(string $title, int $declinations = 1): Product
    {
        $product = $this->fixtures->product($this->category, $this->taxRule, Currency::getDefaultCurrency(), [
            'title' => $title,
            'locale' => self::LOCALE,
            'basePrice' => self::CATALOGUE_PRICE,
            'baseQuantity' => 5,
        ]);

        for ($i = 2; $i <= $declinations; ++$i) {
            $declination = $this->fixtures->productSaleElement($product, ['ref' => $title.'-'.$i, 'quantity' => 5]);
            $this->fixtures->productPrice($declination, Currency::getDefaultCurrency(), ['price' => (string) self::CATALOGUE_PRICE, 'promoPrice' => (string) self::CATALOGUE_PRICE]);
        }

        return $product;
    }

    /**
     * A customer of a group whose price list prices the products at 80 against 100 in the catalogue.
     */
    private function customerOfAPriceList(Product ...$products): Customer
    {
        $group = $this->fixtures->customerGroup();
        $connection = $this->getPropelConnection();
        $list = (new PriceList())
            ->setCode('PRO-'.bin2hex(random_bytes(3)))
            ->setCurrencyId(Currency::getDefaultCurrency()->getId())
            ->setPriority(10)
            ->setExclusive(0)
            ->setActive(1);
        $list->save($connection);
        $this->assign($list, $group);

        foreach ($products as $product) {
            (new PriceListItem())
                ->setPriceListId($list->getId())
                ->setProductSaleElementsId($product->getDefaultSaleElements()->getId())
                ->setMinQuantity(1)
                ->setPrice((string) self::PRICE_LIST_PRICE)
                ->save($connection);
        }

        return $this->fixtures->customer($this->fixtures->customerTitle(), ['customerGroup' => $group]);
    }

    private function assign(PriceList $list, CustomerGroup $group): void
    {
        (new PriceListAssignment())->setPriceListId($list->getId())->setCustomerGroupId($group->getId())->save($this->getPropelConnection());
    }

    private function shopShows(string $mode): void
    {
        ConfigQuery::write('price_display_mode', $mode, false);
        $this->forgetPriceMemos();
    }

    private function productShows(Product $product, string $mode): void
    {
        $product->setPriceDisplayMode($mode)->save($this->getPropelConnection());
        $this->forgetPriceMemos();
    }

    /**
     * The shop reads its settings and what its prices depend on once per process.
     */
    private function forgetPriceMemos(): void
    {
        ConfigQuery::resetCache();
        $this->getService('services_resetter')->reset();
        $this->getService(SeoRequestMemo::class)->reset();
    }

    /**
     * @return array<string, mixed> the Product node of the structured data of the page
     */
    private function productNode(Product $product): array
    {
        $this->getService(SeoRequestMemo::class)->reset();
        $html = $this->getService(SeoToolsService::class)->getSeoMicroData('product', (int) $product->getId());

        preg_match_all('#<script[^>]*>(.*?)</script>#s', $html, $blocks);

        foreach ($blocks[1] as $block) {
            $data = json_decode($block, true, flags: \JSON_THROW_ON_ERROR);

            if ('Product' === ($data['@type'] ?? null)) {
                return $data;
            }
        }

        self::fail('no Product in the structured data of the page');
    }

    /**
     * @param array<mixed> $node
     */
    private function assertNoAmount(array $node): void
    {
        $json = json_encode($node, \JSON_THROW_ON_ERROR);

        foreach (['"offers"', '"price"', '"lowPrice"', '"highPrice"', '"priceCurrency"', '100', '120', '80', '96'] as $needle) {
            self::assertStringNotContainsString($needle, $json, 'readable in '.$json);
        }
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

    private function service(): ProductStructuredData
    {
        return new ProductStructuredData(
            $this->getService(EventDispatcherInterface::class),
            $this->getService(TaxCalculatorFactoryInterface::class),
            $this->getService(VisitorPrices::class),
        );
    }

    /**
     * @return array<string, string>
     */
    private function variableValues(Product $product): array
    {
        $this->getService(SeoRequestMemo::class)->reset();
        $service = $this->getService(MetaTemplateService::class);
        $service->reset();

        return $service->getVariableValues('product', $product->getId(), self::LOCALE);
    }

    private function money(float $amount): string
    {
        return $this->getService(FormatService::class)->money($amount, Currency::getDefaultCurrency()->getId(), self::LOCALE);
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
}
