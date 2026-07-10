<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace SEOne\EventListeners;

use SEOne\Event\SEOneStoreMicroDataEvent;
use SEOne\Event\SEOneStoreMicroDataEvents;
use SEOne\Service\LocalBusinessFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Upgrades the default store microdata (Organization) to a richer LocalBusiness node on every
 * object-based view (product, category, folder, content, page...).
 *
 * @see https://schema.org/LocalBusiness
 */
#[AsEventListener(event: SEOneStoreMicroDataEvents::BETTER_SEO_STORE_MICRO_DATA)]
final readonly class StoreMicroDataListener
{
    public function __construct(
        private LocalBusinessFactory $localBusinessFactory,
    ) {
    }

    public function __invoke(SEOneStoreMicroDataEvent $event): void
    {
        $business = ['@context' => 'https://schema.org'] + $this->localBusinessFactory->build($event->getLocale());

        $event->setStoreMicrodata($business);
    }
}
