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

namespace SEOne\Tests\Integration\Service\SeoDefaultModels;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SEOne\SEOne;
use SEOne\Service\SeoDefaultModels\CategorySEO;
use SEOne\Service\SeoToolsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Category;
use Thelia\Model\Currency;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The structured data of a category page lists the products of one page. A page or a limit that
 * has the wrong shape in the query string gives the first page and the limit of the shop, as if
 * it was not there: the page is shown, not an error.
 */
final class CategoryQueryStringTest extends IntegrationTestCase
{
    private const int SHOP_LIMIT = 1;

    private FixtureFactory $fixtures;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = $this->createFixtureFactory();
        $this->category = $this->fixtures->category();
        $taxRule = $this->fixtures->taxRule();

        foreach (['First', 'Second', 'Third'] as $title) {
            $this->fixtures->product($this->category, $taxRule, Currency::getDefaultCurrency(), [
                'title' => $title,
                'locale' => 'en_US',
                'basePrice' => 10.0,
                'baseQuantity' => 1,
            ]);
        }

        SEOne::setConfigValue(SEOne::BETTER_SE0_LIMIT_CONFIG_KEY, (string) self::SHOP_LIMIT);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedQueries(): iterable
    {
        yield 'page as an array' => ['page[]=1'];
        yield 'limit as an array' => ['limit[]=1'];
        yield 'limit that is not a number' => ['limit=abc'];
        yield 'page that is not a number' => ['page=abc'];
        yield 'limit of zero' => ['limit=0'];
        yield 'negative page' => ['page=-3'];
        yield 'both malformed' => ['page[a]=1&limit[]=2'];
    }

    #[Test]
    #[DataProvider('malformedQueries')]
    public function aMalformedPageOrLimitGivesTheFirstPageWithTheLimitOfTheShop(string $queryString): void
    {
        $items = $this->listedProducts($queryString);

        self::assertCount(self::SHOP_LIMIT, $items);
        self::assertSame($this->listedProducts(''), $items);
    }

    #[Test]
    public function aWellFormedLimitAndPageStillApply(): void
    {
        self::assertCount(2, $this->listedProducts('limit=2'));
        self::assertCount(1, $this->listedProducts('limit=2&page=2'));
    }

    #[Test]
    public function aVeryLargeLimitIsNotFollowed(): void
    {
        self::assertCount(self::SHOP_LIMIT, $this->listedProducts('limit=1000000'));
    }

    #[Test]
    public function aPageParameterOfTheRequestThatIsAnArrayIsIgnored(): void
    {
        $requestStack = $this->getService(RequestStack::class);
        $requestStack->push(Request::create('/?_view[]=category&category_id[]=1'));

        try {
            $tools = $this->getService(SeoToolsService::class);

            self::assertNull($tools->getPageView());
        } finally {
            $requestStack->pop();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listedProducts(string $queryString): array
    {
        $requestStack = $this->getService(RequestStack::class);
        $requestStack->push(Request::create('/?'.$queryString));

        try {
            $script = $this->getService(CategorySEO::class)->getSeoMicroData($this->category->getId(), 'category');
        } finally {
            $requestStack->pop();
        }

        self::assertGreaterThan(0, preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $script, $blocks), $script);

        foreach ($blocks[1] as $json) {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

            if ('ItemList' === ($data['@type'] ?? null)) {
                return $data['itemListElement'];
            }
        }

        self::fail('No ItemList in '.$script);
    }
}
