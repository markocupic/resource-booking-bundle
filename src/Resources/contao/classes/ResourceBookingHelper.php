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
     * @param $objResource
     * @param $slotStartTime
     * @param $slotEndTime
     * @return bool
     */
    public function isResourceBooked($objResource, $slotStartTime, $slotEndTime)
    {
        if (ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objResource, $slotStartTime, $slotEndTime) === null)
        {
            return false;
        }
        return true;
    }

    /**
     * @param $startTstamp
     * @param $endTstamp
     * @param bool $injectEmptyLine
     * @return array
     */
    public static function getWeekSelection($startTstamp, $endTstamp, $injectEmptyLine = false)
    {
        $arrWeeks = array();

        $currentTstamp = $startTstamp;
        while ($currentTstamp <= $endTstamp)
        {
            // add empty
            if ($injectEmptyLine && DateHelper::getMondayOfCurrentWeek() == $currentTstamp)
            {
                $arrWeeks[] = array(
                    'tstamp'     => '',
                    'date'       => '',
                    'optionText' => '-------------'
                );
            }
            $tstampMonday = $currentTstamp;
            $dateMonday = Date::parse('d.m.Y', $currentTstamp);
            $tstampSunday = strtotime($dateMonday . ' + 6 days');
            $dateSunday = Date::parse('d.m.Y', $tstampSunday);
            $calWeek = Date::parse('W', $tstampMonday);
            $yearMonday = Date::parse('Y', $tstampMonday);
            $arrWeeks[] = array(
                'tstamp'       => $currentTstamp,
                'tstampMonday' => $tstampMonday,
                'tstampSunday' => $tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan'      => $dateMonday . ' - ' . $dateSunday,
                'calWeek'      => $calWeek,
                'year'         => $yearMonday,
                'optionText'   => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday)
            );

            $currentTstamp = DateHelper::addDaysToTime(7, $currentTstamp);
        }

        return $arrWeeks;
    }

}
