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

namespace SEOne\EventListeners;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\ViewCheckEvent;

/**
 * Remembers, on the request, the page the shop agreed to serve.
 *
 * The url of a hidden brand, product, category, folder or content resolves to its view before
 * the view check refuses it, and depending on the core the not found page is then rendered with
 * that view still on the request. Whatever SEOne prints from the view alone, such as the language
 * versions of the page, would then tell a hidden page apart from an url that leads nowhere.
 *
 * A view check that refuses a page throws, which stops the dispatch: this listener, registered
 * after every other one, only runs for a page that is actually served.
 */
final readonly class ServedViewListener implements EventSubscriberInterface
{
    private const string SERVED_VIEW_ATTRIBUTE = '_seone_served_view';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::VIEW_CHECK => ['rememberTheServedView', -1024],
        ];
    }

    public function rememberTheServedView(ViewCheckEvent $event): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(
            self::SERVED_VIEW_ATTRIBUTE,
            self::servedViewKey($event->getView(), $event->getViewId()),
        );
    }

    public static function isServed(Request $request, string $view, mixed $viewId): bool
    {
        return $request->attributes->get(self::SERVED_VIEW_ATTRIBUTE) === self::servedViewKey($view, $viewId);
    }

    private static function servedViewKey(mixed $view, mixed $viewId): string
    {
        return (\is_scalar($view) ? (string) $view : '').':'.(\is_scalar($viewId) ? (string) $viewId : '');
    }
}
