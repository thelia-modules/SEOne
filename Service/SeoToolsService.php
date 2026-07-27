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

namespace SEOne\Service;

use SEOne\Event\AlternateHreflangEvent;
use SEOne\Event\SEOneBreadcrumbEvent;
use SEOne\Event\SEOneSpecificEvents\SEOneMicroDataEvent;
use SEOne\Event\SEOneSpecificEvents\SEOnePageDescEvent;
use SEOne\Event\SEOneSpecificEvents\SEOnePageH1Event;
use SEOne\Event\SEOneSpecificEvents\SEOnePageTitleEvent;
use SEOne\Event\SEOneUrlEvent;
use SEOne\Event\SEOneUrlEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Base\LangQuery;

readonly class SeoToolsService
{
    public function __construct(
        private RequestStack             $requestStack,
        private EventDispatcherInterface $dispatcher,
        private SeoManager               $seoDefaultManager,
    )
    {
    }

    private function getRequest(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function getSeoPageTitle(string $view, ?int $view_id): string
    {
        $pageTitleEvent = new SEOnePageTitleEvent(view: $view, view_id: $view_id);

        $this->dispatcher->dispatch(
            event: $pageTitleEvent,
            eventName: SEOnePageTitleEvent::BETTER_SEO_PAGE_TITLE
        );

        return $pageTitleEvent->getTitle() ?? '';
    }

    public function getSeoPageH1(string $view, ?int $view_id): string
    {
        $pageH1Event = new SEOnePageH1Event(view: $view, view_id: $view_id);

        $this->dispatcher->dispatch(
            event: $pageH1Event,
            eventName: SEOnePageH1Event::BETTER_SEO_PAGE_H1
        );

        return $pageH1Event->getTitle() ?? '';
    }

    public function getSeoPageDesc(string $view, ?int $view_id): string
    {
        $pageDescEvent = new SEOnePageDescEvent(view: $view, view_id: $view_id);
        $this->dispatcher->dispatch(
            event: $pageDescEvent,
            eventName: SEOnePageDescEvent::BETTER_SEO_PAGE_DESC
        );
        return $pageDescEvent->getDesc() ?? '';
    }

    public function getSeoMicroData(string $view, ?int $view_id, array $params = []): string
    {
        $microDataEvent = new SEOneMicroDataEvent(view: $view, view_id: $view_id, parameters: $params);

        $this->dispatcher->dispatch(
            event: $microDataEvent,
            eventName: SEOneMicroDataEvent::BETTER_SEO_MICRO_DATA
        );

        return $microDataEvent->getData() ?? '';
    }

    public function getSeoBreadcrumb(string $view, ?int $view_id, array $params = []): array
    {
        $breadcrumbEvent = new SEOneBreadcrumbEvent(view: $view, view_id: $view_id, parameters: $params);

        $this->dispatcher->dispatch(
            event: $breadcrumbEvent,
            eventName: SEOneBreadcrumbEvent::BETTER_SEO_BREADCRUMB
        );

        return $breadcrumbEvent->getBreadcrumb();
    }

    public function getPageId(string $view): ?string
    {
        $key = $view . '_id';
        $type = $this->getRequest()->get($key);
        if ($type) {
            return $type;
        }
        $seoService = $this->seoDefaultManager->getSeoServiceByView(view: $view);

        if (!$seoService) {
            return null;
        }

        return $this->getRequest()->get($seoService?->getIdentifier()) ?? null;
    }

    public function getPageView(): ?string
    {
        return $this->getRequest()->get('_view');
    }

    public function getPageCanonical(): string
    {
        $canonicalUrlEvent = new SEOneUrlEvent();

        $this->dispatcher->dispatch(
            event: $canonicalUrlEvent,
            eventName: SEOneUrlEvents::GENERATE_CANONICAL
        );

        return $canonicalUrlEvent->getUrl();
    }

    public function getHreflang(): string
    {
        $request = $this->getRequest();

        $currentLocale = $request->getSession()->getLang()->getLocale();

        $langs = LangQuery::create()
            ->filterByVisible(true)
            ->find();

        $metas = [];

        $defaultHrefLang = null;
        $currentHrefLang = null;

        foreach ($langs as $lang) {
            $event = new AlternateHreflangEvent($lang, $request);

            $this->dispatcher->dispatch($event, AlternateHreflangEvent::BASE_EVENT_NAME);

            $hreflang = strtolower(str_replace('_', '-', $lang->getLocale()));

            if (0 === (int) AlternateHreflangEvent::CONFIG_KEY_HREFLANG_FORMAT) {
                $hreflang = strtolower(explode('_', $lang->getLocale())[0]);
            }

            if (!empty($event->getUrl())) {
                if ($lang->getByDefault()) {
                    $defaultHrefLang = '<link rel="alternate" hreflang="x-default" href="' . $event->getUrl() . '">';
                }

                if ($lang->getLocale() === $currentLocale) {
                    $currentHrefLang = '<link rel="alternate" hreflang="' . $hreflang . '" href="' . $event->getUrl() . '">';
                } else {
                    $metas[] = '<link rel="alternate" hreflang="' . $hreflang . '" href="' . $event->getUrl() . '">';
                }
            }
        }
        return implode('', array_merge($metas, [$currentHrefLang ?? '',$defaultHrefLang ?? '' ] ));
    }
}
