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

namespace SEOne\Service;

use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * The address of the shop, without trailing slash, in a language: the domain of the language when the
 * shop serves one domain per language (its German pages must not name the French domain as their
 * home or their store), else `url_site`, else the address the request came in on (development).
 */
final readonly class ShopUrl
{
    public function __construct(
        private LangService $langService,
    ) {
    }

    public function base(?string $locale = null): string
    {
        if (ConfigQuery::isMultiDomainActivated()) {
            $lang = $this->langService->getLang();

            if (null !== $locale && $locale !== $lang?->getLocale()) {
                $lang = LangQuery::create()->findOneByLocale($locale);
            }

            $url = rtrim((string) $lang?->getUrl(), '/');

            if ('' !== $url) {
                return $url;
            }
        }

        $url = rtrim((string) ConfigQuery::read('url_site'), '/');

        return '' !== $url ? $url : rtrim(URL::getInstance()->getIndexPage(), '/');
    }
}
