<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Date;
use Contao\Config;

/**
 * Class DateHelper
 * @package Markocupic\ResourceBookingBundle
 */
class DateHelper
{

    /**
     * @param $intDays
     */
    public static function addDaysToTime($intDays = 0, $time = null)
    {
        if ($time === null)
        {
            $time = time();
        }
        if ($intDays < 0)
        {
            $intDays = abs($intDays);
            $strAddDays = '-' . $intDays . ' days';
        }
        else
        {
            $strAddDays = '+' . $intDays . ' days';
        }

        return strtotime(Date::parse('Y-m-d H:i:s', $time) . ' ' . $strAddDays);
    }

    /**
     * @return false|int
     */
    public static function getMondayOfCurrentWeek()
    {
        return strtotime('monday this week');
    }

    /**
     * @param $dateString
     * @return bool
     */
    public static function isValidBookingTime($dateString)
    {
        $format = 'H:i';
        $dateObj = \DateTime::createFromFormat($format, $dateString);
        return $dateObj && $dateObj->format($format) == $dateString;
    }

    /**
     * @param int $time
     * @return array
     */
    public static function getTimeArray($time = 0)
    {
        if ($time === 0)
        {
            $time = time();
        }
        // Send request time
        return array(
            'tstamp' => $time,
            'time'   => Date::parse('H:i:s', $time),
            'date'   => Date::parse(Config::get('dateFormat'), $time),
            'datim'  => Date::parse(Config::get('datimFormat'), $time),
        );
    }

}
