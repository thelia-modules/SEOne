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

namespace SEOne\Twig\Plugins;

use SEOne\Service\SeoToolsService;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SEOneMicroDataPluginTwig extends AbstractExtension
{
    public function __construct(
        private readonly SeoToolsService $toolsService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('SEOneMicroData', [$this, 'getSeoMicroData']),
            new TwigFunction('SEOnePageTitle', [$this, 'getSeoPageTitle']),
            new TwigFunction('SEOnePageDesc', [$this, 'getSeoPageDesc']),
            new TwigFunction('SEOnePageH1', [$this, 'getSeoPageH1']),
            new TwigFunction('SEOnePageCanonical', [$this, 'getSeoCanonical']),
            new TwigFunction('SEOneBreadcrumb', [$this, 'getSeoBreadcrumb']),
            new TwigFunction('SEOneBreadcrumbJsonLd', [$this, 'getSeoBreadcrumbJsonLd']),
            new TwigFunction('SEOneHreflang', [$this, 'getHreflang']),
        ];
    }

    public function getSeoPageTitle(?string $view = null, ?int $id = null): string
    {
        $defaultType = $view ?? $this->toolsService->getPageView() ?? '';

        return $this->toolsService->getSeoPageTitle(view: $defaultType, view_id: $this->resolveViewId($defaultType, $id));
    }

    public function getSeoPageH1(?string $view = null, ?string $id = null): string
    {
        $defaultType = $view ?? $this->toolsService->getPageView() ?? '';
        $defaultId = $this->resolveViewId($defaultType, $id);

        if (null === $defaultId) {
            return '';
        }

        return $this->toolsService->getSeoPageH1(view: $defaultType, view_id: $defaultId);
    }

    public function getSeoPageDesc(?string $view = null, ?string $id = null): string
    {
        $defaultType = $view ?? $this->toolsService->getPageView() ?? '';

        return $this->toolsService->getSeoPageDesc(view: $defaultType, view_id: $this->resolveViewId($defaultType, $id));
    }

    public function getSeoMicroData(?string $view = null, array $params = []): string
    {
        $defaultType = $view ?? $this->toolsService->getPageView() ?? '';

        return $this->toolsService->getSeoMicroData(
            view: $defaultType,
            view_id: $this->resolveViewId($defaultType, $params['id'] ?? null),
            params: $params
        );
    }

    public function getSeoCanonical(): string
    {
        return $this->toolsService->getPageCanonical();
    }

    public function getHreflang(): string
    {
        return $this->toolsService->getHreflang();
    }

    public function getSeoBreadcrumb(?string $view = null, array $params = []): array
    {
        $defaultType = $view ?? $this->toolsService->getPageView() ?? '';

        return $this->toolsService->getSeoBreadcrumb(
            view: $defaultType,
            view_id: $this->resolveViewId($defaultType, $params['id'] ?? null),
            params: $params
        );
    }

    /**
     * The view id reaches us as a string, either from the query string through getPageId()
     * or from a template argument, while SeoToolsService expects ?int. Anything that is not
     * a number means there is no SEO target to describe.
     */
    private function resolveViewId(string $view, mixed $id = null): ?int
    {
        $id ??= $this->toolsService->getPageId($view);

        return is_numeric($id) ? (int) $id : null;
    }

    public function getSeoBreadcrumbJsonLd(?array $breadcrumb): string
    {
        if (empty($breadcrumb)) {
            return '';
        }

        $itemListElement = [];
        $position = 1;

        foreach (array_merge([$this->getHomeItem()], $breadcrumb) as $item) {
            $name = $item['title'] ?? null;

            if (null === $name || '' === $name) {
                continue;
            }

            $url = $item['url'] ?? null;

            // @id is optional for the current page, but must never be an empty string.
            $itemNode = (null === $url || '' === $url) ? ['name' => $name] : ['@id' => $url, 'name' => $name];

            // The position is assigned here, once the home item and the breadcrumb are
            // merged, so that the list is numbered 1..n without duplicate or hole.
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'item' => $itemNode,
            ];
        }

        if ([] === $itemListElement) {
            return '';
        }

        return '<script type="application/ld+json">'.json_encode([
            '@context' => 'https://schema.org/',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement,
        ]).'</script>';
    }

    /**
     * @return array{url: ?string, title: ?string}
     */
    private function getHomeItem(): array
    {
        $url = ConfigQuery::read('url_site');

        // url_site is often left empty in development: fall back to the URL the request
        // came in on rather than emitting an empty @id.
        if (null === $url || '' === $url) {
            $url = URL::getInstance()->getIndexPage();
        }

        return [
            'url' => $url,
            'title' => ConfigQuery::read('store_name'),
        ];
    }
}
