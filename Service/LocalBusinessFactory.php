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

namespace SEOne\Service;

use SEOne\SEOne;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;

/**
 * Single source of truth for the store LocalBusiness JSON-LD node, built from the Thelia
 * store configuration. Reused by the store-microdata listener (global object views) and by
 * the Home/Contact SEO services.
 *
 * The returned node carries no @context: callers embed it in a graph (which owns the
 * @context) or wrap it themselves for a standalone <script>.
 *
 * The business image/logo is taken from the $image argument when provided, otherwise from the
 * SEOne "store_image" configuration value (absolute URL); when both are empty it is omitted.
 *
 * @see https://schema.org/LocalBusiness
 */
final readonly class LocalBusinessFactory
{
    public function __construct(
        private LangService $langService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?string $locale = null, ?string $image = null): array
    {
        $locale ??= $this->langService->getLocale();
        $image ??= SEOne::getConfigValue('store_image');
        $siteUrl = rtrim((string) ConfigQuery::read('url_site'), '/');
        $country = CountryQuery::create()->filterById((int) ConfigQuery::read('store_country', 64))->findOne();

        $business = [
            '@type' => 'LocalBusiness',
            '@id' => $siteUrl.'/#business',
            'name' => ConfigQuery::read('store_name'),
            'description' => SEOne::getConfigValue('description', ConfigQuery::read('store_description'), $locale),
            'url' => $siteUrl.'/',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => trim(implode(' ', array_filter([
                    ConfigQuery::read('store_address1'),
                    ConfigQuery::read('store_address2'),
                    ConfigQuery::read('store_address3'),
                ]))),
                'addressLocality' => ConfigQuery::read('store_city'),
                'postalCode' => ConfigQuery::read('store_zipcode'),
                'addressCountry' => $country?->getIsoalpha2(),
            ],
        ];

        if ($image) {
            $business['image'] = $image;
            $business['logo'] = $image;
        }
        if ($phone = ConfigQuery::read('store_phone')) {
            $business['telephone'] = $phone;
        }
        if ($fax = ConfigQuery::read('store_fax')) {
            $business['faxNumber'] = $fax;
        }
        if ($email = ConfigQuery::read('store_email')) {
            $business['email'] = $email;
        }

        // Extension points — fill in once the data is available in configuration:
        //   $business['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => ..., 'longitude' => ...];
        //   $business['openingHoursSpecification'] = [...];
        //   $business['sameAs'] = ['https://www.facebook.com/...', ...];
        //   $business['priceRange'] = '€€';

        return $business;
    }
}
