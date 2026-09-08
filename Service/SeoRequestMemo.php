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

use SEOne\Model\Map\SeoneI18nTableMap;
use SEOne\Model\Seone;
use SEOne\Model\SeoneQuery;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The SEO row of one object, read once per request.
 *
 * Rendering a single page asks for the same row several times — the h1 of the page, then
 * the robots meta and the extra JSON-LD of the same object — and each of those was a
 * query of its own. They all need the same four translated columns, so one read answers
 * them all.
 *
 * The memo lives for one request: it is cleared by the framework between requests and
 * console commands (ResetInterface), so a long-running worker never serves a stale row.
 */
class SeoRequestMemo implements ResetInterface
{
    /**
     * @var array<string, Seone|null>
     */
    private array $rows = [];

    public function getRow(?string $objectType, int|string|null $objectId, string $locale): ?Seone
    {
        $key = $objectType.'|'.$objectId.'|'.$locale;

        if (\array_key_exists($key, $this->rows)) {
            return $this->rows[$key];
        }

        return $this->rows[$key] = SeoneQuery::create()
            ->filterByObjectId($objectId)
            ->filterByObjectType($objectType)
            ->useSEOneI18nQuery()
            ->filterByLocale($locale)
            ->endUse()
            ->withColumn(SeoneI18nTableMap::COL_NOINDEX, 'noindex')
            ->withColumn(SeoneI18nTableMap::COL_NOFOLLOW, 'nofollow')
            ->withColumn(SeoneI18nTableMap::COL_H1, 'h1')
            ->withColumn(SeoneI18nTableMap::COL_JSON_DATA, 'json_data')
            ->findOne();
    }

    public function reset(): void
    {
        $this->rows = [];
    }
}
