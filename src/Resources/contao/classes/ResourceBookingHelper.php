<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Database;
use Contao\Date;
use Contao\ResourceBookingModel;

/**
 * Class ResourceBookingHelper
 * @package Markocupic\ResourceBookingBundle
 */
class ResourceBookingHelper
{

    /**
     * @param $objRes
     * @param $slotStartTime
     * @param $slotEndTime
     * @return bool
     */
    public function isResourceBooked($objRes, $slotStartTime, $slotEndTime)
    {
        if (static::getBookedResourcesInSlot($objRes, $slotStartTime, $slotEndTime) === null)
        {
            return false;
        }
        return true;
    }

    /**
     * @param $objRes
     * @param $slotStartTime
     * @param $slotEndTime
     * @return \Contao\Model\Collection|null
     */
    public function getBookedResourcesInSlot($objRes, $slotStartTime, $slotEndTime)
    {
        $arrIDS = array();
        // 1. possible case  -|---- | or -|-----| or -|-----|--
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking WHERE (startTime<? AND endTime>?) AND pid=?')
            ->execute($slotStartTime, $slotStartTime, $objRes->id);
        if ($objDb->numRows)
        {
            while ($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        // 2. possible case  |----- | or |-----| or |-----|--
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking WHERE (startTime=? AND endTime>?) AND pid=?')
            ->execute($slotStartTime, $slotStartTime, $objRes->id);
        if ($objDb->numRows)
        {
            while ($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        // 3. possible case  | --- | or -| ----| or | ----|--
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking WHERE (startTime>? AND startTime<? AND endTime>?) AND pid=?')
            ->execute($slotStartTime, $slotEndTime, $slotEndTime, $objRes->id);
        if ($objDb->numRows)
        {
            while ($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        // 4. possible case  |----|
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking WHERE (startTime=? AND endTime=?) AND pid=?')
            ->execute($slotStartTime, $slotEndTime, $objRes->id);
        if ($objDb->numRows)
        {
            while ($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        if (count($arrIDS) > 0)
        {
            $arrIDS = array_unique($arrIDS);
            return ResourceBookingModel::findMultipleByIds($arrIDS);
        }

        return null;
    }

    /**
     * @param $start
     * @param $end
     * @param bool $injectEmptyLine
     * @return array
     */
    public static function getWeekSelection($start, $end, $injectEmptyLine = false)
    {
        $arrWeeks = array();
        for ($i = $start; $i <= $end; $i++)
        {
            // add empty
            if ($injectEmptyLine && DateHelper::getMondayOfCurrentWeek() == strtotime('monday ' . (string)$i . ' week'))
            {
                $arrWeeks[] = array(
                    'tstamp'     => '',
                    'date'       => '',
                    'optionText' => '-------------'
                );
            }
            $tstampMonday = strtotime('monday ' . (string)$i . ' week');
            $dateMonday = Date::parse('d.m.Y', $tstampMonday);
            $tstampSunday = strtotime($dateMonday . ' + 6 days');
            $dateSunday = Date::parse('d.m.Y', $tstampSunday);
            $calWeek = Date::parse('W', $tstampMonday);
            $yearMonday = Date::parse('Y', $tstampMonday);
            $arrWeeks[] = array(
                'tstamp'       => strtotime('monday ' . (string)$i . ' week'),
                'tstampMonday' => $tstampMonday,
                'tstampSunday' => $tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan'      => $dateMonday . ' - ' . $dateSunday,
                'calWeek'      => $calWeek,
                'year'         => $yearMonday,
                'optionText'   => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday)
            );
        }

        return $arrWeeks;
    }

}
