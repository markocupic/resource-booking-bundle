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

use Markocupic\ResourceBookingBundle\Config\RbbConfig;

/**
 * Date calculations of the week calendar in the default time zone.
 *
 * Pure functions: the current time is never read here, the callers pass it
 * (see ClockInterface). Days and weeks are added in wall clock time, so a slot
 * keeps its time of day across a daylight saving time change.
 */
final class DateHelper
{
    private function __construct()
    {
    }

    /**
     * Returns the midnight of the first day of the week the timestamp is in.
     *
     * $beginWeek is the first day of the week (tl_resource_booking_time_slot_type.beginWeek),
     * e.g. "monday"; an invalid value falls back to RbbConfig::RBB_DEFAULT_BEGIN_WEEK.
     *
     * "<weekday> this week" must not be used here: PHP always counts the week from
     * monday, so "sunday this week" would return the sunday at the end of the week
     * instead of the sunday at its beginning.
     */
    public static function getFirstDayOfWeek(string $beginWeek, int $timestamp): int
    {
        if (!\in_array($beginWeek, RbbConfig::RBB_WEEKDAYS, true)) {
            $beginWeek = RbbConfig::RBB_DEFAULT_BEGIN_WEEK;
        }

        $date = new \DateTimeImmutable(date('Y-m-d', $timestamp));

        // RBB_WEEKDAYS starts with sunday (0) like the "w" format character
        if ((string) array_search($beginWeek, RbbConfig::RBB_WEEKDAYS, true) === $date->format('w')) {
            return $date->getTimestamp();
        }

        return $date->modify('last '.$beginWeek)->getTimestamp();
    }

    /**
     * Adds (or with a negative number subtracts) days, keeping the time of day.
     */
    public static function addDays(int $days, int $timestamp): int
    {
        return self::modify(\sprintf('%+d days', $days), $timestamp);
    }

    /**
     * Adds (or with a negative number subtracts) weeks, keeping the time of day.
     */
    public static function addWeeks(int $weeks, int $timestamp): int
    {
        return self::modify(\sprintf('%+d weeks', $weeks), $timestamp);
    }

    /**
     * Returns true for a time "hh:mm" (00:00 - 23:59) or "24:00".
     */
    public static function isValidBookingTime(string $time): bool
    {
        if ('24:00' === $time) {
            return true;
        }

        $date = \DateTimeImmutable::createFromFormat('H:i', $time);

        return false !== $date && $date->format('H:i') === $time;
    }

    private static function modify(string $modifier, int $timestamp): int
    {
        $timestamp = strtotime($modifier, $timestamp);

        if (false === $timestamp) {
            throw new \RuntimeException(\sprintf('Could not apply "%s" to the timestamp.', $modifier));
        }

        return $timestamp;
    }
}
