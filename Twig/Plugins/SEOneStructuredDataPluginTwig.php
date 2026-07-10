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

namespace SEOne\Twig\Plugins;

use SEOne\Service\LocalBusinessFactory;
use SEOne\Service\SeoToolsService;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes one Twig function per schema.org type so themes can drop structured data exactly
 * where they want it, e.g. in <head>:
 *   {{ SEOneWebSite() }}
 *   {{ SEOneWebPage() }}
 *   {{ SEOneContactPage() }}
 *   {{ SEOneLocalBusiness(absolute_url(asset('dist/images/logo.png'))) }}
 *   {{ SEOneBreadcrumbList([{ name: 'Home', url: '/' }, { name: 'Contact' }]) }}
 *
 * Each function returns a standalone <script type="application/ld+json"> block. Nodes are
 * linked through shared @id anchors (#website, #webpage, #business) so they cross-reference
 * even when emitted as separate blocks.
 *
 * @see https://schema.org/WebSite
 * @see https://schema.org/WebPage
 * @see https://schema.org/ContactPage
 * @see https://schema.org/LocalBusiness
 * @see https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
 */
class SEOneStructuredDataPluginTwig extends AbstractExtension
{
    public function __construct(
        private readonly LocalBusinessFactory $localBusinessFactory,
        private readonly SeoToolsService $toolsService,
        private readonly LangService $langService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('SEOneWebSite', [$this, 'renderWebSite'], ['is_safe' => ['html']]),
            new TwigFunction('SEOneWebPage', [$this, 'renderWebPage'], ['is_safe' => ['html']]),
            new TwigFunction('SEOneContactPage', [$this, 'renderContactPage'], ['is_safe' => ['html']]),
            new TwigFunction('SEOneLocalBusiness', [$this, 'renderLocalBusiness'], ['is_safe' => ['html']]),
            new TwigFunction('SEOneBreadcrumbList', [$this, 'renderBreadcrumbList'], ['is_safe' => ['html']]),
        ];
    }

    public function renderWebSite(): string
    {
        $siteUrl = $this->siteUrl();

        return $this->script([
            '@type' => 'WebSite',
            '@id' => $siteUrl.'/#website',
            'url' => $siteUrl.'/',
            'name' => ConfigQuery::read('store_name'),
            'inLanguage' => $this->language(),
            'publisher' => ['@id' => $siteUrl.'/#business'],
        ]);
    }

    public function renderWebPage(?string $name = null, ?string $description = null, ?string $url = null): string
    {
        return $this->script($this->pageNode('WebPage', 'webpage', $name, $description, $url));
    }

    public function renderContactPage(?string $name = null, ?string $description = null, ?string $url = null): string
    {
        return $this->script($this->pageNode('ContactPage', 'contactpage', $name, $description, $url));
    }

    public function renderLocalBusiness(?string $image = null): string
    {
        return $this->script($this->localBusinessFactory->build($this->langService->getLocale(), $image));
    }

    /**
     * @param array<int, array{name?: string, url?: string}> $items ordered from root to current page
     */
    public function renderBreadcrumbList(array $items): string
    {
        if ([] === $items) {
            return '';
        }

        $elements = [];
        foreach (array_values($items) as $index => $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'] ?? '',
            ];
            if (!empty($item['url'])) {
                $element['item'] = $item['url'];
            }
            $elements[] = $element;
        }

        return $this->script([
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageNode(string $type, string $anchor, ?string $name, ?string $description, ?string $url): array
    {
        $siteUrl = $this->siteUrl();
        $view = $this->toolsService->getPageView() ?? '';
        $viewId = null !== ($id = $this->toolsService->getPageId($view)) ? (int) $id : null;

        $name ??= $this->toolsService->getSeoPageTitle($view, $viewId);
        $description ??= $this->toolsService->getSeoPageDesc($view, $viewId);
        $url ??= $this->toolsService->getPageCanonical();

        return [
            '@type' => $type,
            '@id' => $url.'#'.$anchor,
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'inLanguage' => $this->language(),
            'isPartOf' => ['@id' => $siteUrl.'/#website'],
            'about' => ['@id' => $siteUrl.'/#business'],
        ];
    }

    private function siteUrl(): string
    {
        return rtrim((string) ConfigQuery::read('url_site'), '/');
    }

    private function language(): string
    {
        return str_replace('_', '-', $this->langService->getLocale());
    }

    /**
     * @param array<string, mixed> $node
     */
    private function script(array $node): string
    {
        return '<script type="application/ld+json">'
            .json_encode(['@context' => 'https://schema.org'] + $node, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
            .'</script>';
    }
}
