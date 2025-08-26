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

namespace SEOne\Service\SeoDefaultModels;

use SEOne\SEOne;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Domain\Localization\LangService;

readonly class DefaultSEO implements SeoElementInterface
{
    use SEOneMicroDataTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
    }

    public function supports(string $view): bool
    {
        return $view === $this->getView();
    }

    public function getIdentifier(): string
    {
        return 'default';
    }

    public function getView(): string
    {
        return 'default';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        return '';
    }

    public function getSeoPageTitle($id): string
    {
        return SEOne::getConfigValue('title', ConfigQuery::read('store_name'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageDesc($id): string
    {
        return SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $this->langService->getLocale()) ?? '';
    }

    public function getSeoPageH1($id, string $type): string
    {
        return '';
    }

    private function getDefaultMicroData(): array
    {
        return [];
    }

    public function getSeoBreadcrumb($id): array
    {
        return [];
    }
}
