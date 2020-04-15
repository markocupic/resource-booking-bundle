<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Contao;

/**
 * Class ResourceBookingResourceModel
 * @package Contao
 */
class ResourceBookingResourceModel extends Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_resource';

    /**
     * @param $intId
     * @return Model\Collection|null
     */
    public static function findPublishedByPid($intId)
    {
        $arrIds = [];
        $objDb = static::findByPid($intId);
        if ($objDb !== null)
        {
            while ($objDb->next())
            {
                if ($objDb->published)
                {
                    // Return if parent is published too
                    $objParent = $objDb->getRelated('pid');
                    if ($objParent !== null)
                    {
                        if ($objParent->published)
                        {
                            $arrIds[] = $objDb->id;
                        }
                    }
                }
            }
        }

        return static::findMultipleByIds($arrIds);
    }

    /**
     * @param $intId
     * @return ResourceBookingResourceModel
     */
    public static function findPublishedByPk($intId)
    {
        $arrColumn = ['id=?', 'published=?'];
        $arrValues = [$intId, '1'];
        $objDb = self::findOneBy($arrColumn, $arrValues);
        if ($objDb !== null)
        {
            // Return if parent is published too
            $objParent = $objDb->getRelated('pid');
            if ($objParent !== null)
            {
                if ($objParent->published)
                {
                    return $objDb;
                }
            }
        }

        return null;
    }

    /**
     * @param $intId
     * @param $intPid
     * @return mixed
     */
    public static function findPublishedByPkAndPid($intId, $intPid)
    {
        $arrColumn = ['id=?', 'pid=?', 'published=?'];
        $arrValues = [$intId, $intPid, '1'];

        $objDb = self::findOneBy($arrColumn, $arrValues);
        if ($objDb !== null)
        {
            // Return if parent is published too
            $objParent = $objDb->getRelated('pid');
            if ($objParent !== null)
            {
                if ($objParent->published)
                {
                    return $objDb;
                }
            }
        }

        return null;
    }

    /**
     * @param $arrIds
     * @return mixed
     */
    public static function findMultipleAndPublishedByIds($arrIds)
    {
        $arrIdsNew = [];
        if (($objDb = static::findMultipleByIds($arrIds)) !== null)
        {
            while ($objDb->next())
            {
                if ($objDb->published)
                {
                    // Return if parent is published too
                    $objParent = $objDb->getRelated('pid');
                    if ($objParent !== null)
                    {
                        if ($objParent->published)
                        {
                            $arrIdsNew[] = $objDb->id;
                        }
                    }
                }
            }
        }

        return static::findMultipleByIds($arrIdsNew);
    }

    /**
     * @param array $arrPids
     * @return Model\Collection|null
     */
    public static function findMultipleAndPublishedByPids(array $arrPids)
    {
        if (empty($arrPids))
        {
            return null;
        }

        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking_resource WHERE pid IN(' . implode(',', $arrPids) . ') AND published=?')->execute('1');
        $arrIds = $objDb->fetchEach('id');
        return static::findMultipleByIds($arrIds);
    }

}
