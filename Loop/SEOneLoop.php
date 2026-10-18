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

namespace SEOne\Loop;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use SEOne\Model\Map\SeoneI18nTableMap;
use SEOne\Model\Seone;
use SEOne\Model\SeoneQuery;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\LangQuery;

/**
 * @method getObjectId()
 * @method getObjectType()
 * @method getLangId()
 */
class SEOneLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createAlphaNumStringTypeArgument('object_id'),
            Argument::createAlphaNumStringTypeArgument('object_type'),
            Argument::createIntTypeArgument('lang_id')
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $objectId = $this->getObjectId();
        $objectType = $this->getObjectType();
        $langId = $this->getLangId();

        $lang = LangQuery::create()
            ->filterById($langId)
            ->findOne();

        $query = SeoneQuery::create()
            ->filterByObjectId($objectId)
            ->filterByObjectType($objectType)
            ->useSEOneI18nQuery()
            ->filterByLocale($lang->getLocale())
            ->endUse()
            ->withColumn(SeoneI18nTableMap::COL_NOINDEX, 'noindex')
            ->withColumn(SeoneI18nTableMap::COL_NOFOLLOW, 'nofollow')
            ->withColumn(SeoneI18nTableMap::COL_H1, 'h1')
            ->withColumn(SeoneI18nTableMap::COL_JSON_DATA, 'json_data');

        for ($i = 1; $i <= 5; ++$i) {
            if(!defined(SeoneI18nTableMap::class.'::COL_MESH_TEXT_'.$i) ||
                !defined(SeoneI18nTableMap::class.'::COL_MESH_URL_'.$i) ||
                !defined(SeoneI18nTableMap::class.'::COL_MESH_'.$i)) {
                continue;
            }
            $query->withColumn(\constant(SeoneI18nTableMap::class.'::COL_MESH_TEXT_'.$i), 'mesh_text_'.$i);
            $query->withColumn(\constant(SeoneI18nTableMap::class.'::COL_MESH_URL_'.$i), 'mesh_url_'.$i);
            $query->withColumn(\constant(SeoneI18nTableMap::class.'::COL_MESH_'.$i), 'mesh_'.$i);
        }

        return $query;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var Seone $data */
        foreach ($loopResult->getResultDataCollection() as $data) {
            $loopResultRow = new LoopResultRow($data);

            $loopResultRow->set('ID', $data->getId());
            $loopResultRow->set('OBJECT_ID', $data->getObjectId());
            $loopResultRow->set('OBJECT_TYPE', $data->getObjectType());
            $loopResultRow->set('NOINDEX', $data->getVirtualColumn('noindex'));
            $loopResultRow->set('NOFOLLOW', $data->getVirtualColumn('nofollow'));
            $loopResultRow->set('H1', $data->getVirtualColumn('h1'));
            $loopResultRow->set('JSON_DATA', $data->getVirtualColumn('json_data'));

            for ($i = 1; $i <= 5; ++$i) {
                $loopResultRow->set('MESH_TEXT_'.$i, $data->getVirtualColumn('mesh_text_'.$i));
                $loopResultRow->set('MESH_URL_'.$i, $data->getVirtualColumn('mesh_url_'.$i));
                $loopResultRow->set('MESH_'.$i, $data->getVirtualColumn('mesh_'.$i));
            }

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
