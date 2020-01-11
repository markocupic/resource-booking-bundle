<?php

declare(strict_types=1);

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
        $intBackWeeks = Config::get('rbb_intBackWeeks');
        $intAheadWeeks = Config::get('rbb_intAheadWeeks');

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

}
