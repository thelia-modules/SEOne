<?php

namespace SEOne\EventListeners;

use SEOne\Event\AlternateHreflangEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

class AlternateHreflangListener implements EventSubscriberInterface
{
    private const array CATALOGUE_VIEWS = ['brand', 'product', 'folder', 'content', 'category'];

    public static function getSubscribedEvents() : array
    {
        return [
            AlternateHreflangEvent::BASE_EVENT_NAME => [
                'generateAlternateHreflang', 128
            ]
        ];
    }

    public function generateAlternateHreflang(AlternateHreflangEvent $event) : void
    {
        $view = $event->getRequest()->attributes->get('_view');

        $multiDomainActivated = ConfigQuery::isMultiDomainActivated();

        $uri = null;

        // A catalogue page offers its own language versions only once the shop agreed to serve
        // it: the not found page of a hidden one must not name it (see ServedViewListener).
        if (\in_array($view, self::CATALOGUE_VIEWS, true)
            && ServedViewListener::isServed($event->getRequest(), $view, $event->getRequest()->attributes->get($view.'_id'))
        ) {
            $uri = $this->findUrlFromView($event, $view, $view.'_id');
        }

        if (null === $uri) {
            $uri = $event->getRequest()->getRequestUri();
            if (!$multiDomainActivated) {
                if (preg_match('/lang=[a-zA-Z_]{5}/', $uri)) {
                    $uri = preg_replace('/lang=[a-zA-Z_]{5}/', 'lang=' . $event->getLang()->getLocale(), $uri);
                } elseif (\strpos($uri, '?')) {
                    $uri .= '&lang=' . $event->getLang()->getLocale();
                } else {
                    $uri .= '?lang=' . $event->getLang()->getLocale();
                }
            }
        }

        if ($multiDomainActivated) {
            $baseUrl = (string) $event->getLang()->getUrl();

            $uri = trim($uri, '/');

            // remove lang for home page
            if (preg_match('/^lang=[a-zA-Z_]{5}$/', $uri)) {
                $uri = '';
            }
        } else {
            $baseUrl = ConfigQuery::getConfiguredShopUrl();
            if(empty($baseUrl)) {
                $baseUrl = $event->getRequest()->getSchemeAndHttpHost();
            }

            $uri = trim($uri, '/');
        }

        $url = trim($baseUrl, '/') . '/' . $uri;

        $event->setUrl($url);
    }

    protected function findUrlFromView(AlternateHreflangEvent $event, $view, $requestAttributeKey) : ?string
    {
        $id = $event->getRequest()->attributes->get($requestAttributeKey);

        if (!empty($id)) {
            if (null !== $rewritingRetriever = URL::getInstance()->retrieve($view, $id, $event->getLang()->getLocale())) {
                $url =  !empty($rewritingRetriever->rewrittenUrl) ? $rewritingRetriever->rewrittenUrl : $rewritingRetriever->url;

                return $this->generateUriFromUrl($url);
            }
        }

        return null;
    }

    protected function generateUriFromUrl($url) : ?string
    {
        $url = parse_url($url);

        if (!empty($url['path'])) {
            return $url['path'] . (!empty($url['query']) ? '?' . $url['query'] : '');
        }

        return null;
    }
}
