<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Util;

use Contao\Date;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;

class DateHelper
{
    /**
     * @throws \Exception
     */
    public static function addDaysToTime(int $intDays = 0, int|null $time = null): int
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

        $tstamp = strtotime(Date::parse('Y-m-d H:i:s', $time).' '.$strAddDays);

        if (false !== $tstamp) {
            return $tstamp;
        }

        throw new \Exception('Could not generate a valid timestamp.');
    }

    /**
     * Return begin weekday of the week the timestamp is in
     * By default this is a monday.
     *
     * @throws \Exception
     */
    public static function getFirstDayOfWeek(array $arrAppConfig, $tstamp = null): int
    {
        if (null === $tstamp) {
            $tstamp = time();
        }

        $date = new \DateTime(Date::parse('Y-m-d', $tstamp));

        $date->setTime(0, 0, 0);

        $beginWeek = $arrAppConfig['beginWeek'];

        // RBB_WEEKDAYS starts with sunday (0) like the "w" format character.
        $key = array_search($beginWeek, RbbConfig::RBB_WEEKDAYS, true);

        if ((string) $key === $date->format('w')) {
            // If the date is already configured begin week day, return it as-is
            return $date->getTimestamp();
        }

        // Otherwise, return the date of the nearest "begin week day" in the past
        // by default this is a monday
        return $date->modify(\sprintf('last %s', $beginWeek))->getTimestamp();
    }

    public static function isValidBookingTime(string $dateString): bool
    {
        if ('24:00' === $dateString) {
            return true;
        }

        $format = 'H:i';
        $dateObj = \DateTime::createFromFormat($format, $dateString);

        return false !== $dateObj && $dateObj->format($format) === $dateString;
    }

    /**
     * @throws \Exception
     */
    public static function addWeeksToTime(int $intWeeks = 0, int|null $time = null): int
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

        $tstamp = strtotime(Date::parse('Y-m-d H:i:s', $time).' '.$strAddWeeks);

        if (false !== $tstamp) {
            return $tstamp;
        }

        throw new \Exception('Could not generate a valid timestamp.');
    }

    /**
     * By default, this is the timestamp of a monday.
     */
    public static function getFirstDayOfCurrentWeek(array $arrAppConfig, int|null $timestamp = null): int
    {
        if (!$timestamp) {
            $timestamp = time();
        }

        $beginWeek = $arrAppConfig['beginWeek'];

        return strtotime(\sprintf('%s this week', $beginWeek), $timestamp);
    }
}
