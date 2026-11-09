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

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Thelia\Model\Currency;

/**
 * What the visitor of the page is shown, as the core of the shop answers it: whether the price of a
 * product is shown to them at all, and the price they pay.
 *
 * Both services are optional: a core that has neither knows only the catalogue price, which every
 * visitor is shown. Then nothing is hidden and no price is resolved, and the callers keep the
 * catalogue price they compute themselves. They are read from a locator by service id, and no type
 * of this class names them, so the module loads, and is analysed, against a core that does not
 * have their classes.
 */
final readonly class VisitorPrices
{
    private const VISIBILITY_RESOLVER = 'Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver';

    private const PRICE_READER = 'Thelia\Domain\Catalog\Product\VisitorPriceReader';

    public function __construct(
        #[AutowireLocator([
            self::VISIBILITY_RESOLVER => '?'.self::VISIBILITY_RESOLVER,
            self::PRICE_READER => '?'.self::PRICE_READER,
        ])]
        private ?ContainerInterface $coreServices = null,
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
        $visibility = $this->coreService(self::VISIBILITY_RESOLVER);

        if (null === $visibility || [] === $productIds || !method_exists($visibility, 'productsWithHiddenPrice')) {
            return [];
        }

        $reader = $this->coreService(self::PRICE_READER);
        $customer = null !== $reader && method_exists($reader, 'currentCustomer') ? $reader->currentCustomer() : null;

        return $visibility->productsWithHiddenPrice($productIds, $customer);
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
        $reader = $this->coreService(self::PRICE_READER);

        if (null === $reader || [] === $saleElementIds || !method_exists($reader, 'resolve')) {
            return [];
        }

        $prices = [];

        foreach ($reader->resolve($saleElementIds, $currency) as $saleElementId => $price) {
            $prices[$saleElementId] = $price->chargedPrice();
        }

        return $prices;
    }

    private function coreService(string $id): ?object
    {
        if (null === $this->coreServices || !$this->coreServices->has($id)) {
            return null;
        }

        $service = $this->coreServices->get($id);

        return \is_object($service) ? $service : null;
    }
}
