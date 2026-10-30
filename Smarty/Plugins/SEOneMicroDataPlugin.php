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

namespace SEOne\Smarty\Plugins;

use SEOne\Service\SeoToolsService;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class SEOneMicroDataPlugin extends AbstractSmartyPlugin
{
    public function __construct(
        private readonly SeoToolsService $toolsService,

    ) {
    }

    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor('function', 'SEOneMicroData', $this, 'getSeoMicroData'),
            new SmartyPluginDescriptor('function', 'SEOnePageTitle', $this, 'getSeoPageTitle'),
            new SmartyPluginDescriptor('function', 'SEOnePageH1', $this, 'getSeoPageH1'),
        ];
    }

    public function getSeoPageTitle($params): string
    {
        $defaultType = $params['type'] ?? $this->toolsService->getPageView() ?? '';
        $defaultId = $params['id'] ?? $this->toolsService->getPageId($defaultType);
        return $this->toolsService->getSeoPageTitle(view: $defaultType,view_id: $defaultId);
    }

    public function getSeoPageH1($params): string
    {
        $defaultType = $params['type'] ?? $this->toolsService->getPageView() ?? '';
        $defaultId = $params['id'] ?? $this->toolsService->getPageId($defaultType);
        if (null === $defaultId) {
            return '';
        }
        return $this->toolsService->getSeoPageH1(view: $defaultType,view_id: $defaultId);
    }

    public function getSeoMicroData($params): string
    {
        $defaultType = $params['type'] ?? $this->toolsService->getPageView() ?? '';
        $defaultId = $params['id'] ?? $this->toolsService->getPageId($defaultType);
        return $this->toolsService->getSeoMicroData(view: $defaultType, view_id: $defaultId, params: $params);
    }
}
