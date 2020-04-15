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
 * Class ResourceBookingTimeSlotModel
 * @package Contao
 */
class ResourceBookingTimeSlotModel extends Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_time_slot';

    /**
     * @param $intPid
     * @return Model\Collection|null
     */
    public static function findPublishedByPid($intPid)
    {
        $arrIds = [];

        $objDb = static::findByPid($intPid);
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

        $arrOptions = [
            'order' => 'tl_resource_booking_time_slot.sorting ASC',
        ];

        return static::findMultipleByIds($arrIds, $arrOptions);
    }

}
