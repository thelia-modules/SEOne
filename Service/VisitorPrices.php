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

use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Model\Currency;

/**
 * What the visitor of the page is shown, as the core of the shop answers it: whether the price of a
 * product is shown to them at all, and the price they pay.
 *
 * Both services are optional: a core that has neither knows only the catalogue price, which every
 * visitor is shown. Then nothing is hidden and no price is resolved, and the callers keep the
 * catalogue price they compute themselves.
 */
final readonly class VisitorPrices
{
    public function __construct(
        private ?PriceVisibilityResolver $visibility = null,
        private ?VisitorPriceReader $reader = null,
    ) {
    }

    /**
     * The products whose price the visitor is not shown (a shop or a product that shows its prices to
     * identified customers only, or on request): their structured data tells no amount.
     *
     * @param list<int> $productIds
     *
     * @return list<int>
     */
    public function hiddenAmong(array $productIds): array
    {
        if (null === $this->visibility || [] === $productIds) {
            return [];
        }

        return $this->visibility->productsWithHiddenPrice($productIds, $this->reader?->currentCustomer());
    }

    /**
     * The untaxed price the visitor pays for each declination, promotion included: the price of
     * their price list, of their group, else the catalogue one less their discounts. Empty, without
     * a query, while nothing prices this visitor beyond the catalogue: the caller keeps its own.
     *
     * @param list<int> $saleElementIds
     *
     * @return array<int, float> by declination id, a declination without a price left out
     */
    public function untaxedPrices(array $saleElementIds, Currency $currency): array
    {
        if (null === $this->reader || [] === $saleElementIds) {
            return [];
        }

        $prices = [];

        foreach ($this->reader->resolve($saleElementIds, $currency) as $saleElementId => $price) {
            $prices[$saleElementId] = $price->chargedPrice();
        }

        return $prices;
    }
}
