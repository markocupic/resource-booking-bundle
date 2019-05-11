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
        $arrRes = array();
        $objRes = static::findByPid($intId);
        if ($objRes !== null)
        {
            while ($objRes->next())
            {
                if ($objRes->published)
                {
                    $arrRes[] = $objRes->id;
                }
            }
        }

        return static::findMultipleByIds($arrRes);
    }

    /**
     * @param $intId
     * @return ResourceBookingResourceModel
     */
    public static function findPublishedByPk($intId)
    {
        $arrColumn = array('id=?', 'published=?');
        $arrValues = array($intId, '1');
        return self::findOneBy($arrColumn, $arrValues);
    }

}
