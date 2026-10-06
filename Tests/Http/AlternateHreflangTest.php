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

namespace SEOne\Tests\Http;

use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Propel;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Brand;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The language versions SEOne prints in the head of a front-office page: a served catalogue page
 * offers its own urls, and the not found page of a hidden one offers the same links as an url that
 * leads nowhere, so that the answer does not reveal what the shop keeps to itself.
 */
final class AlternateHreflangTest extends WebIntegrationTestCase
{
    private const string BRAND_URL = 'seone-hreflang-brand.html';

    private const string PRODUCT_URL = 'seone-hreflang-product.html';

    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $frontTemplate = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate();

        if (!file_exists($frontTemplate->getAbsolutePath().\DIRECTORY_SEPARATOR.'brand.html.twig')) {
            self::markTestSkipped('The installed front-office theme has no brand page.');
        }

        // Built on the connection, not through createFixtureFactory(): no synthetic request
        // must become the main request of the client's calls.
        $this->fixtures = new FixtureFactory(Propel::getConnection('TheliaMain'));

        // The core keeps the parser of the last render in a static: after a not found page, the
        // next request of the same process would render with it.
        (new \ReflectionProperty(ParserResolver::class, 'currentParser'))->setValue(null, null);
    }

    #[Test]
    public function aVisibleBrandOffersTheUrlOfEachOfItsLanguageVersions(): void
    {
        $brand = $this->brand();
        $brand->setRewrittenUrl('fr_FR', 'fr-'.self::BRAND_URL);

        $this->assertPageRenders('/'.self::BRAND_URL);

        $alternates = $this->alternates();

        self::assertContains('<link rel="alternate" hreflang="fr" href="http://localhost/fr-'.self::BRAND_URL.'">', $alternates);
        self::assertContains('<link rel="alternate" hreflang="en" href="http://localhost/'.self::BRAND_URL.'">', $alternates);
    }

    #[Test]
    public function aHiddenBrandOffersTheSameLanguageVersionsAsAnUnknownUrl(): void
    {
        $this->client->request('GET', '/'.self::BRAND_URL);
        $unknown = $this->alternates();

        $this->brand(['visible' => 0]);

        $this->client->request('GET', '/'.self::BRAND_URL);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertNotSame([], $unknown);
        self::assertSame($unknown, $this->alternates());
    }

    #[Test]
    public function aHiddenProductOffersTheSameLanguageVersionsAsAnUnknownUrl(): void
    {
        $this->client->request('GET', '/'.self::PRODUCT_URL);
        $unknown = $this->alternates();

        $product = $this->fixtures->product(
            $this->fixtures->category(),
            $this->fixtures->taxRule(),
            $this->fixtures->currency(),
            ['visible' => 0],
        );
        $product->setRewrittenUrl('en_US', self::PRODUCT_URL);

        $this->client->request('GET', '/'.self::PRODUCT_URL);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertNotSame([], $unknown);
        self::assertSame($unknown, $this->alternates());
    }

    /**
     * The view named by its own path names no brand: its not found page offers the language
     * versions of the url asked for, like any other.
     */
    #[Test]
    public function theBrandViewWithoutABrandOffersTheLanguageVersionsOfTheUrlAskedFor(): void
    {
        $this->client->request('GET', '/brand');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertContains('<link rel="alternate" hreflang="fr" href="http://localhost/brand?lang=fr_FR">', $this->alternates());
    }

    private function brand(array $overrides = []): Brand
    {
        $brand = $this->fixtures->brand(['title' => 'SEOne hreflang brand'] + $overrides);
        $brand->setRewrittenUrl('en_US', self::BRAND_URL);

        return $brand;
    }

    /**
     * @return list<string>
     */
    private function alternates(): array
    {
        preg_match_all('/<link rel="alternate" hreflang="[^"]*" href="[^"]*">/', (string) $this->client->getResponse()->getContent(), $links);

        return $links[0];
    }
}
