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

use SEOne\Event\SEOneUrlEvent;
use SEOne\Event\SEOneUrlEvents;
use SEOne\Model\SeoneQuery;
use SEOne\SEOne;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\MetaDataQuery;

class CanonicalUrlListener implements EventSubscriberInterface
{
    protected Session $session;

    public function __construct(protected RequestStack $requestStack)
    {
    }

    public function generateUrlCanonical(SEOneUrlEvent $event): void
    {
        /** @var Request $request */
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return;
        }

        if ($event->getUrl() !== null) {
            return;
        }

        if (null !== $canonicalOverride = $this->getCanonicalOverride()) {
            try {
                $event->setUrl($canonicalOverride);

                return;
            } catch (\InvalidArgumentException $e) {
                Tlog::getInstance()->addWarning($e->getMessage());
            }
        }

        $parseUrlByCurrentLocale = $this->getParsedUrlByCurrentLocale();

        if (empty($parseUrlByCurrentLocale['host'])) {
            return;
        }

        // Be sure to use the proper domain name
        $canonicalUrl = $parseUrlByCurrentLocale['scheme'].'://'.$parseUrlByCurrentLocale['host'];

        // preserving a potential subdirectory, e.g. http://somehost.com/mydir/index.php/...
        $canonicalUrl .= $request->getBaseUrl();

        // Remove script name from path, e.g. http://somehost.com/index.php/...
        $canonicalUrl = preg_replace("!/index(_dev)?\.php!", '', $canonicalUrl);

        $path = $request->getPathInfo();

        if (!empty($path) && $path != '/') {
            $canonicalUrl .= $path;

            $canonicalUrl = rtrim($canonicalUrl, '/');
            $view = $this->inputValue($request, '_view');

            $page = $this->inputValue($request, 'page');
            if (null !== $page && $page !== '1' && \in_array($view, ['category', 'folder'])) {
                $canonicalUrl .= '?page='.$page;
            }
        } elseif (isset($parseUrlByCurrentLocale['query'])) {
            $canonicalUrl .= '/?'.((\array_key_exists('query', $parseUrlByCurrentLocale)) ? $parseUrlByCurrentLocale['query'] : '');
        }

        try {
            $event->setUrl($canonicalUrl);
        } catch (\InvalidArgumentException $e) {
            Tlog::getInstance()->addWarning($e->getMessage());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SEOneUrlEvents::GENERATE_CANONICAL => [
                'generateUrlCanonical', 128,
            ],
        ];
    }

    /**
     * At least one element will be present within the array.
     * Potential keys within this array are:
     * scheme - e.g. http
     * host
     * port
     * user
     * pass
     * path
     * query - after the question mark ?
     * fragment - after the hashmark #.
     */
    protected function getParsedUrlByCurrentLocale(): false|array|int|string|null
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // for one domain by lang
        if ((int) ConfigQuery::read('one_domain_foreach_lang', 0) === 1) {
            // We always query the DB here, as the Lang configuration (then the related URL) may change during the
            // user session lifetime, and improper URLs could be generated. This is quite odd, okay, but may happen.
            $langUrl = LangQuery::create()->findPk($request->getSession()->getLang()->getId())->getUrl();

            if (!empty($langUrl) && false !== $parse = parse_url($langUrl)) {
                return $parse;
            }
        }

        // Configured site URL
        $urlSite = ConfigQuery::read('url_site');
        if (!empty($urlSite) && false !== $parse = parse_url($urlSite)) {
            return $parse;
        }

        // return current URL
        return parse_url($request->getUri());
    }

    protected function getCanonicalOverride(): ?string
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $lang = $request->getSession()->getLang();

        $routeParameters = $this->getRouteParameters();

        if (null === $routeParameters) {
            return null;
        }

        $url = null;

        $metaCanonical = MetaDataQuery::create()
            ->filterByMetaKey(SEOne::SEO_CANONICAL_META_KEY)
            ->filterByElementKey($routeParameters['view'])
            ->filterByElementId($routeParameters['id'])
            ->findOne();

        if (null !== $metaCanonical) {
            $canonicalValues = json_decode($metaCanonical->getValue(), true);

            $url = isset($canonicalValues[$lang->getLocale()]) && !empty($canonicalValues[$lang->getLocale()]) ? $canonicalValues[$lang->getLocale()] : null;
        }

        // Try to get old field of SEOneModule
        if (null === $url && class_exists("SEOne\Seone")) {
            try {
                $betterSeoData = SeoneQuery::create()
                    ->filterByObjectType($routeParameters['view'])
                    ->filterByObjectId($routeParameters['id'])
                    ->findOne();

                $url = $betterSeoData->setLocale($lang->getLocale())
                    ->getCanonicalField();
            } catch (\Throwable) {
                // Catch if field doesn't exist but do nothing
            }
        }

        if (null === $url) {
            return null;
        }

        if (false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return rtrim($this->getSiteBaseUrlForLocale($lang), '/').'/'.$url;
        }

        return $url;
    }

    protected function getSiteBaseUrlForLocale(?Lang $lang = null)
    {
        if (null === $lang) {
            $lang = $this->requestStack->getCurrentRequest()->getSession()->getLang();
        }
        if ((int) ConfigQuery::read('one_domain_foreach_lang', 0) === 1) {
            // We always query the DB here, as the Lang configuration (then the related URL) may change during the
            // user session lifetime, and improper URLs could be generated. This is quite odd, okay, but may happen.
            return LangQuery::create()->findPk($lang->getId())->getUrl();
        }

        // Configured site URL
        return ConfigQuery::read('url_site');
    }

    protected function getRouteParameters(): ?array
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $view = $this->inputValue($request, 'view');
        if (null === $view) {
            $view = $this->inputValue($request, '_view');
        }
        if (null === $view) {
            return null;
        }

        $id = $this->inputValue($request, $view.'_id');

        if (null === $id) {
            return null;
        }

        return compact('view', 'id');
    }

    /**
     * Reads a request input from the same sources, and in the same order, as the
     * Request::get() this replaces (deprecated since Symfony 7.4).
     *
     * The three sources are all in use here: the routers put `_view` in the attributes,
     * RewritingRouter puts `<view>_id` and the extra parameters such as `page` in the
     * query, and a legacy `?view=...` URL or a posted form can carry either.
     *
     * Values are read through all(), like Request::get() did, so that a crafted
     * `?page[]=1` yields no value instead of the HTTP 400 that InputBag::get() would
     * raise on a front-office page.
     *
     * Typed against the base Symfony request on purpose: a sub-request rendered through
     * render(controller(...)) is not a Thelia request, and this listener runs on whatever
     * request is current.
     */
    private function inputValue(HttpRequest $request, string $key): ?string
    {
        foreach ([$request->attributes, $request->query, $request->request] as $bag) {
            $value = $bag->all()[$key] ?? null;

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
