<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Date;

use Contao\Date;
use Contao\Config;

/**
 * Class DateHelper
 * @package Markocupic\ResourceBookingBundle\Date
 */
class DateHelper
{

    /**
     * @param int $intDays
     * @param int|null $time
     * @return false|int
     */
    public static function addDaysToTime(int $intDays = 0, int $time = null): int
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
     * @param int $intWeeks
     * @param null $time
     * @return false|int
     */
    public static function addWeeksToTime(int $intWeeks = 0, int $time = null)
    {
        if ($time === null)
        {
            $time = time();
        }
        if ($intWeeks < 0)
        {
            $intWeeks = abs($intWeeks);
            $strAddWeeks = '-' . $intWeeks . ' weeks';
        }
        else
        {
            $strAddWeeks = '+' . $intWeeks . ' weeks';
        }

        return strtotime(Date::parse('Y-m-d H:i:s', $time) . ' ' . $strAddWeeks);
    }

    /**
     * @return false|int
     */
    public static function getMondayOfCurrentWeek(): int
    {
        return strtotime('monday this week');
    }

    /**
     * @param null $tstamp
     * @return int
     */
    function getMondayOfWeekDate($tstamp = null): int
    {
        if($tstamp === null)
        {
            $tstamp = time();
        }

        $date = new \DateTime(Date::parse('Y-m-d', $tstamp));

        $date->setTime(0, 0, 0);

        if ($date->format('N') === 1) {
            // If the date is already a Monday, return it as-is
            return $date->getTimestamp();
        } else {
            // Otherwise, return the date of the nearest Monday in the past
            // This includes Sunday in the previous week instead of it being the start of a new week
            return $date->modify('last monday')->getTimestamp();
        }
    }

    /**
     * @param $dateString
     * @return bool
     */
    public static function isValidBookingTime(string $dateString): bool
    {
        $format = 'H:i';
        $dateObj = \DateTime::createFromFormat($format, $dateString);

        return $dateObj !== false && $dateObj->format($format) === $dateString;
    }

    /**
     * Check if date is in range
     * @param int $tstamp
     * @return bool
     */
    public static function isValidDate(int $tstamp): bool
    {
        $intBackWeeks = (int) Config::get('rbb_intBackWeeks');
        $intAheadWeeks = (int) Config::get('rbb_intAheadWeeks');

        // Get the timestamp of the first and last possible weeks
        $tstampFirstPossibleWeek = static::addWeeksToTime($intBackWeeks, static::getMondayOfCurrentWeek());
        $tstampLastPossibleWeek = static::addWeeksToTime($intAheadWeeks, static::getMondayOfCurrentWeek());

        if ($tstamp < $tstampFirstPossibleWeek || $tstamp > $tstampLastPossibleWeek)
        {
            return false;
        }
        // Get numeric value of the weekday:  0 for sunday, 1 for monday, etc.
        if (Date::parse('w', $tstamp) !== '1')
        {
            return false;
        }

        return true;
    }

    /**
     * @param int $tstampStart
     * @param int $tstampEnd
     * @return int
     */
    public static function calculateWeeksBetween(int$tstampStart, int$tstampEnd): int
    {
        $mondayCurrentWeek = static::getMondayOfCurrentWeek();
    }

}
