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
use SEOne\Service\LocalBusinessFactory;
use SEOne\Service\SeoRequestMemo;
use SEOne\Service\SeoToolsService;
use SEOne\Twig\Plugins\SEOneMicroDataPluginTwig;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;

/**
 * On a shop with one domain per language, the pages of a language name their own domain as their home
 * and as the address of the store; a product's breadcrumb follows its default category.
 */
final class LanguageDomainTest extends IntegrationTestCase
{
    private const string DOMAIN = 'https://www.shop-language.test';

    private ?Lang $lang = null;

    private ?string $previousUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lang = $this->getService(LangService::class)->getLang();
        self::assertNotNull($this->lang);
        $this->previousUrl = $this->lang->getUrl();
        $this->lang->setUrl(self::DOMAIN);
        ConfigQuery::write('one_domain_foreach_lang', 1);
        // The home item of a breadcrumb is named after the store.
        ConfigQuery::write('store_name', 'Shop');
    }

    protected function tearDown(): void
    {
        // The rows are rolled back with the transaction; the language held by the session and the
        // settings cached by the core are not.
        $this->lang?->setUrl($this->previousUrl);
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    #[Test]
    public function theHomeOfTheBreadcrumbAndTheStoreAreOnTheDomainOfTheLanguage(): void
    {
        $breadcrumb = $this->getService(SEOneMicroDataPluginTwig::class)->getSeoBreadcrumbJsonLd([['url' => self::DOMAIN.'/page.html', 'title' => 'Page']]);
        $store = $this->getService(LocalBusinessFactory::class)->build($this->lang?->getLocale());

        self::assertStringContainsString('"@id":"'.self::DOMAIN.'"', $breadcrumb);
        self::assertSame(self::DOMAIN.'/', $store['url']);
    }

    #[Test]
    public function theBreadcrumbOfAProductFollowsItsDefaultCategory(): void
    {
        $fixtures = $this->createFixtureFactory();
        $other = $fixtures->category();
        $default = $fixtures->category();
        $product = $fixtures->product($default, $fixtures->taxRule(), Currency::getDefaultCurrency(), ['title' => 'Gloves', 'locale' => (string) $this->lang?->getLocale()]);
        // Filed in another category too, whose row comes first in the table.
        $fixtures->productCategory($product, $other);

        $this->getService(SeoRequestMemo::class)->reset();
        $path = $this->getService(SeoToolsService::class)->getSeoBreadcrumb('product', (int) $product->getId());

        self::assertSame([$default->getUrl($this->lang?->getLocale()), $product->getUrl($this->lang?->getLocale())], array_column($path, 'url'));
    }
}
