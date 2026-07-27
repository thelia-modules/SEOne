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

namespace SEOne\EventListeners;

use SEOne\Event\SEOneBreadcrumbEvent;
use SEOne\Event\SEOneSpecificEvents\SEOneMicroDataEvent;
use SEOne\Event\SEOneSpecificEvents\SEOnePageDescEvent;
use SEOne\Event\SEOneSpecificEvents\SEOnePageH1Event;
use SEOne\Event\SEOneSpecificEvents\SEOnePageTitleEvent;
use SEOne\Service\SeoManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SEOnePageTitleEvent::BETTER_SEO_PAGE_TITLE, method: 'getSeoPageTitle', priority: 128)]
#[AsEventListener(event: SEOnePageH1Event::BETTER_SEO_PAGE_H1, method: 'getSeoPageH1', priority: 128)]
#[AsEventListener(event: SEOneMicroDataEvent::BETTER_SEO_MICRO_DATA, method: 'getSeoMicroData', priority: 128)]
#[AsEventListener(event: SEOnePageDescEvent::BETTER_SEO_PAGE_DESC, method: 'getSeoPageDesc', priority: 128)]
#[AsEventListener(event: SEOneBreadcrumbEvent::BETTER_SEO_BREADCRUMB, method: 'getSeoBreadcrumb', priority: 128)]
readonly class SeoDefaultListener
{
    public function __construct(private SeoManager $defaultManager)
    {
    }

    public function getSeoPageTitle(SEOnePageTitleEvent $event): void
    {
        $title = $this->defaultManager->getSeoPageTitle($event->getViewId(), $event->getView());
        $event->setTitle($title);
    }

    public function getSeoPageH1(SEOnePageH1Event $event): void
    {
        $title = $this->defaultManager->getSeoPageH1($event->getViewId(), $event->getView());
        $event->setTitle($title);
    }

    public function getSeoMicroData(SEOneMicroDataEvent $event): void
    {
        $data = $this->defaultManager->getSeoMicroData($event->getViewId(), $event->getView(), $event->getParameters());
        $event->setData($data);
    }

    public function getSeoBreadcrumb(SEOneBreadcrumbEvent $event): void
    {
        $breadcrumb = $this->defaultManager->getSeoBreadcrumb($event->getViewId(), $event->getView(), $event->getParameters());
        $event->setBreadcrumb($breadcrumb);
    }

    public function getSeoPageDesc(SEOnePageDescEvent $event): void
    {
        $desc = $this->defaultManager->getSeoPageDesc($event->getViewId(), $event->getView());
        $event->setDesc($desc);
    }
}
