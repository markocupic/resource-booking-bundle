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
 * Class ResourceBookingModel
 * @package Contao
 */
class ResourceBookingModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_resource_booking';

    /**
     * @param $objResource
     * @param $starttime
     * @param $endtime
     * @param $memberid
     * @return ResourceBookingModel
     */
    public static function findOneByResourceIdStarttimeEndtimeAndOwnerId($objResource, $starttime, $endtime, $memberid)
    {
        $arrColumn = array('pid=?', 'startTime=?', 'endTime=?', 'member=?');
        $arrValues = array($objResource->id, $starttime, $endtime, $memberid);
        return self::findOneBy($arrColumn, $arrValues);
    }

}
