<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Util;

use Contao\Date;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;

/**
 * Class DateHelper.
 */
class DateHelper
{
    /**
     * @return false|int
     */
    public static function addDaysToTime(int $intDays = 0, int $time = null): int
    {
        if (null === $time) {
            $time = time();
        }

        if ($intDays < 0) {
            $intDays = abs($intDays);
            $strAddDays = '-'.$intDays.' days';
        } else {
            $strAddDays = '+'.$intDays.' days';
        }

        return strtotime(Date::parse('Y-m-d H:i:s', $time).' '.$strAddDays);
    }

    /**
     * @param null $time
     *
     * @return false|int
     */
    public static function addWeeksToTime(int $intWeeks = 0, int $time = null)
    {
        if (null === $time) {
            $time = time();
        }

        if ($intWeeks < 0) {
            $intWeeks = abs($intWeeks);
            $strAddWeeks = '-'.$intWeeks.' weeks';
        } else {
            $strAddWeeks = '+'.$intWeeks.' weeks';
        }

        return strtotime(Date::parse('Y-m-d H:i:s', $time).' '.$strAddWeeks);
    }

    /**
     * By default this is the timestamp of a monday.
     */
    public static function getFirstDayOfCurrentWeek(array $arrAppConfig, int $timestamp = null): int
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $beginnWeek = $arrAppConfig['beginnWeek'];

        return strtotime(sprintf('%s this week', $beginnWeek), $timestamp);
    }

    /**
     * Return beginn weekday of the week the timestamp is in
     * By default this is a monday.
     *
     * @param null $tstamp
     */
    public function getFirstDayOfWeek(array $arrAppConfig, $tstamp = null): int
    {
        if (null === $tstamp) {
            $tstamp = time();
        }

        $date = new \DateTime(Date::parse('Y-m-d', $tstamp));

        $date->setTime(0, 0, 0);

        $beginnWeek = $arrAppConfig['beginnWeek'];

        $key = array_search($beginnWeek, RbbConfig::RBB_WEEKDAYS, true);

        if ($key === $date->format('N')) {
            // If the date is already configured beginn week day, return it as-is
            return $date->getTimestamp();
        }

        // Otherwise, return the date of the nearest "beginn week day" in the past
        // by default this is a monday
        return $date->modify(sprintf('last %s', $beginnWeek))->getTimestamp();
    }

    /**
     * @param $dateString
     */
    public static function isValidBookingTime(string $dateString): bool
    {
        $format = 'H:i';
        $dateObj = \DateTime::createFromFormat($format, $dateString);

        return false !== $dateObj && $dateObj->format($format) === $dateString;
    }

    /**
     * Check if date is in the permitted range.
     */
    public static function isDateInPermittedRange(int $tstamp, array $arrAppConfig): bool
    {
        $intBackWeeks = $arrAppConfig['intBackWeeks'];
        $intAheadWeeks = $arrAppConfig['intAheadWeeks'];

        // Get the timestamp of the first and last possible weeks
        $tstampFirstPermittedWeek = static::addWeeksToTime($intBackWeeks, static::getFirstDayOfCurrentWeek($arrAppConfig));
        $tstampLastPermittedWeek = static::addWeeksToTime($intAheadWeeks, static::getFirstDayOfCurrentWeek($arrAppConfig));

        if ($tstamp < $tstampFirstPermittedWeek || $tstamp > $tstampLastPermittedWeek) {
            return false;
        }
        // Get numeric value of the weekday:  0 for sunday, 1 for monday, etc.
        if ('1' !== Date::parse('w', $tstamp)) {
            return false;
        }

        return true;
    }
}
