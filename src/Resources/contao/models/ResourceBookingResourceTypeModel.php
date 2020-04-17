<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Contao;

/**
 * Class ResourceBookingResourceTypeModel
 * @package Contao
 */
class ResourceBookingResourceTypeModel extends Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_resource_type';

    /**
     * @param $intId
     * @return ResourceBookingResourceTypeModel
     */
    public static function findPublishedByPk($intId)
    {
        $arrColumn = ['id=?', 'published=?'];
        $arrValues = [$intId, '1'];

        return self::findOneBy($arrColumn, $arrValues);
    }

    /**
     * @param $arrIds
     * @return Model\Collection|null
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
                    $arrIdsNew[] = $objDb->id;
                }
            }
        }

        return static::findMultipleByIds($arrIdsNew);
    }

}
