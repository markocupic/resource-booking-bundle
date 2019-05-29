<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Contao;

/**
 * Class ResourceBookingResourceModel
 * @package Contao
 */
class ResourceBookingResourceModel extends \Model
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
        $arrIds = array();
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
        $arrColumn = array('id=?', 'published=?');
        $arrValues = array($intId, '1');
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
        $arrColumn = array('id=?', 'pid=?', 'published=?');
        $arrValues = array($intId, $intPid, '1');

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

}
