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
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;

/**
 * What the visitor of the page is shown, as the core of the shop answers it: whether the price of a
 * product is shown to them at all, and the price they pay.
 *
 * Both services are optional: a core that has neither knows only the catalogue price. Then no price
 * is resolved, and the callers keep the catalogue price they compute themselves; a price is hidden
 * only when the setting `price_display_mode` of the shop says so for the whole shop (a showcase
 * site sets it to `on_request`). They are read from a locator by service id, and no type
 * of this class names them, so the module loads, and is analysed, against a core that does not
 * have their classes.
 */
final readonly class VisitorPrices
{
    private const VISIBILITY_RESOLVER = 'Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver';

    private const PRICE_READER = 'Thelia\Domain\Catalog\Product\VisitorPriceReader';

    /**
     * The setting of the shop the resolver of the core reads (`visible`, `logged_in`, `on_request`). A
     * core without the resolver (3.3) has no use for it, but a showcase site sets it all the same
     * (TheliaCMS): it is read here then, for the whole shop.
     */
    private const SHOP_SETTING = 'price_display_mode';

    public function __construct(
        #[AutowireLocator([
            self::VISIBILITY_RESOLVER => '?'.self::VISIBILITY_RESOLVER,
            self::PRICE_READER => '?'.self::PRICE_READER,
            SecurityContext::class => '?'.SecurityContext::class,
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
        if ([] === $productIds) {
            return [];
        }

        $visibility = $this->coreService(self::VISIBILITY_RESOLVER);

        if (null === $visibility || !method_exists($visibility, 'productsWithHiddenPrice')) {
            return $this->shopHidesPrices() ? array_values(array_unique(array_map('intval', $productIds))) : [];
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

    /**
     * Whether the setting of the shop hides every price from this visitor, on a core that has no
     * resolver to ask: unset, or holding a value this module does not know, it shows them.
     */
    private function shopHidesPrices(): bool
    {
        return match ((string) ConfigQuery::read(self::SHOP_SETTING, 'visible')) {
            'on_request' => true,
            'logged_in' => !$this->customerSignedIn(),
            default => false,
        };
    }

    private function customerSignedIn(): bool
    {
        $securityContext = $this->coreService(SecurityContext::class);

        return $securityContext instanceof SecurityContext && $securityContext->hasAuthenticatedCustomerUser();
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
