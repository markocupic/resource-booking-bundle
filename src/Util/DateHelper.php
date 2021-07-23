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

use Contao\Config;
use Contao\Date;
use Contao\System;
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

    public static function getFirstDayOfCurrentWeek(int $timestamp = null): int
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $beginnWeek = System::getContainer()->getParameter('markocupic_resource_booking.beginnWeek');

        return strtotime(sprintf('%s this week', $beginnWeek), $timestamp);
    }

    /**
     * Return monday of the week the timestamp is in.
     *
     * @param null $tstamp
     */
    public function getFirstDayOfWeek($tstamp = null): int
    {
        if (null === $tstamp) {
            $tstamp = time();
        }

        $date = new \DateTime(Date::parse('Y-m-d', $tstamp));

        $date->setTime(0, 0, 0);

        $beginnWeek = System::getContainer()->getParameter('markocupic_resource_booking.beginnWeek');

        $key = array_search($beginnWeek, RbbConfig::RBB_WEEKDAYS, true);

        if ($key === $date->format('N')) {
            // If the date is already configured beginn week day, return it as-is
            return $date->getTimestamp();
        }

        // Otherwise, return the date of the nearest Monday (beginn week day) in the past
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
     * Check if date is in range.
     */
    public static function isValidDate(int $tstamp): bool
    {
        $intBackWeeks = (int) Config::get('rbb_intBackWeeks');
        $intAheadWeeks = (int) Config::get('rbb_intAheadWeeks');

        // Get the timestamp of the first and last possible weeks
        $tstampFirstPossibleWeek = static::addWeeksToTime($intBackWeeks, static::getFirstDayOfCurrentWeek());
        $tstampLastPossibleWeek = static::addWeeksToTime($intAheadWeeks, static::getFirstDayOfCurrentWeek());

        if ($tstamp < $tstampFirstPossibleWeek || $tstamp > $tstampLastPossibleWeek) {
            return false;
        }
        // Get numeric value of the weekday:  0 for sunday, 1 for monday, etc.
        if ('1' !== Date::parse('w', $tstamp)) {
            return false;
        }

        return true;
    }
}
