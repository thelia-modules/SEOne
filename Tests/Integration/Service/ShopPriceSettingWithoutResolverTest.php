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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SEOne\Service\ProductStructuredData;
use SEOne\Service\VisitorPrices;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * On a core that has no resolver of the prices a visitor is shown (3.3), the setting of the shop
 * `price_display_mode` still hides the price of every product: a showcase site sets it to
 * `on_request` and its products get no offer, so no amount, in their structured data.
 */
final class ShopPriceSettingWithoutResolverTest extends IntegrationTestCase
{
    private const string LOCALE = 'en_US';

    protected function tearDown(): void
    {
        parent::tearDown();

        // The rollback takes back the setting, not the copy the core keeps in memory.
        ConfigQuery::resetCache();
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function settings(): iterable
    {
        yield 'every price shown' => ['visible', 1];
        yield 'prices on request (a showcase site)' => ['on_request', 0];
        yield 'prices to customers, a visitor who did not sign in' => ['logged_in', 0];
        yield 'a value the module does not know' => ['typo', 1];
    }

    #[Test]
    #[DataProvider('settings')]
    public function theSettingOfTheShopDecidesWhetherAProductHasAnOffer(string $setting, int $offers): void
    {
        $product = $this->product();
        ConfigQuery::write('price_display_mode', $setting, false);
        ConfigQuery::resetCache();

        $structuredData = $this->structuredDataOfACoreWithoutResolver();

        self::assertCount($offers, $structuredData->offers($product, self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry()));
        self::assertSame(1 === $offers, \array_key_exists('offers', $structuredData->summaries([(int) $product->getId()], self::LOCALE, Currency::getDefaultCurrency(), Country::getDefaultCountry())[0]));
    }

    /**
     * What the module builds on a core 3.3: the locator holds neither the resolver nor the reader
     * of the prices, only the security context every core has.
     */
    private function structuredDataOfACoreWithoutResolver(): ProductStructuredData
    {
        $securityContext = $this->getService(SecurityContext::class);

        return new ProductStructuredData(
            $this->getService(EventDispatcherInterface::class),
            $this->getService(TaxCalculatorFactoryInterface::class),
            new VisitorPrices(new ServiceLocator([SecurityContext::class => static fn (): object => $securityContext])),
        );
    }

    private function product(): Product
    {
        $fixtures = $this->createFixtureFactory();

        return $fixtures->product($fixtures->category(), $fixtures->taxRule(), Currency::getDefaultCurrency(), [
            'title' => 'Gloves',
            'locale' => self::LOCALE,
            'basePrice' => 100.0,
            'baseQuantity' => 5,
        ]);
    }
}
