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

use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;

/**
 * Evaluates the booking restrictions configured on a resource
 * (tl_resource_booking_resource.weeksBack/weeksAhead,
 * tl_resource_booking_resource.restrictWeekdays/allowedWeekdays and
 * tl_resource_booking_resource.addBookableUntil/bookableUntil and
 * tl_resource_booking_resource.autoConfirm) and the weekdays of a time slot
 * (tl_resource_booking_time_slot.weekdays) and the blocked periods
 * (tl_resource_booking_blocked_period).
 *
 * All methods accept either the resource model or its row array, because the
 * slot classes only keep the row.
 */
class ResourceBookingRestriction
{
    /**
     * Returns true if the resource may be booked on the given weekday
     * (lowercase english weekday name, e.g. "monday").
     *
     * @param ResourceBookingResourceModel|array<string, mixed>|null $resource
     */
    public static function isWeekdayAllowed(ResourceBookingResourceModel|array|null $resource, string $weekday): bool
    {
        $row = self::toArray($resource);

        if (empty($row['restrictWeekdays'])) {
            return true;
        }

        $allowedWeekdays = StringUtil::deserialize($row['allowedWeekdays'] ?? null, true);

        return \in_array(strtolower($weekday), $allowedWeekdays, true);
    }

    /**
     * Returns true if the time slot is available on the given weekday
     * (lowercase english weekday name, e.g. "monday").
     *
     * A time slot without weekdays (NULL or an empty selection, e.g. a record
     * that has not been migrated yet) is available on every weekday.
     *
     * @param ResourceBookingTimeSlotModel|array<string, mixed>|null $timeSlot
     */
    public static function isTimeSlotAvailableOnWeekday(ResourceBookingTimeSlotModel|array|null $timeSlot, string $weekday): bool
    {
        if (null === $timeSlot) {
            return true;
        }

        $weekdays = $timeSlot instanceof ResourceBookingTimeSlotModel ? $timeSlot->weekdays : ($timeSlot['weekdays'] ?? null);
        $weekdays = StringUtil::deserialize($weekdays, true);

        if (empty($weekdays)) {
            return true;
        }

        return \in_array(strtolower($weekday), $weekdays, true);
    }

    /**
     * Returns true if the blocked period (row of tl_resource_booking_blocked_period)
     * applies to the resource and covers the slot from $startTime to $endTime.
     *
     * The slot is covered if its day lies between dateFrom and dateTo (inclusive)
     * and, if the period is limited to a time range, if the slot overlaps that
     * time range. The time range applies to every day of the period.
     *
     * @param array<string, mixed> $period
     * @param array<string, mixed> $resource
     */
    public static function isInBlockedPeriod(array $period, array $resource, int $startTime, int $endTime): bool
    {
        if (empty($period['published'])) {
            return false;
        }

        if (!self::blockedPeriodAppliesToResource($period, $resource)) {
            return false;
        }

        $dayStart = strtotime(date('Y-m-d', $startTime));
        $dateFrom = strtotime(date('Y-m-d', (int) ($period['dateFrom'] ?? 0)));
        $dateTo = strtotime(date('Y-m-d', (int) ($period['dateTo'] ?? 0)));

        if ($dayStart < $dateFrom || $dayStart > $dateTo) {
            return false;
        }

        if (empty($period['addTimeRange'])) {
            return true;
        }

        $timeFrom = self::timeToSeconds($period['timeFrom'] ?? null);
        $timeTo = self::timeToSeconds($period['timeTo'] ?? null);

        // An incomplete time range blocks the whole day.
        if (null === $timeFrom || null === $timeTo) {
            return true;
        }

        return $startTime - $dayStart < $timeTo && $endTime - $dayStart > $timeFrom;
    }

    /**
     * Returns true if the blocked period applies to the resource
     * (scope "all", the resource type or the resource itself has been selected).
     *
     * @param array<string, mixed> $period
     * @param array<string, mixed> $resource
     */
    public static function blockedPeriodAppliesToResource(array $period, array $resource): bool
    {
        $scope = (string) ($period['scope'] ?? 'all');

        return match ($scope) {
            'resourceTypes' => \in_array((int) ($resource['pid'] ?? 0), array_map('intval', StringUtil::deserialize($period['resourceTypes'] ?? null, true)), true),
            'resources' => \in_array((int) ($resource['id'] ?? 0), array_map('intval', StringUtil::deserialize($period['resources'] ?? null, true)), true),
            default => true,
        };
    }

    /**
     * Returns the timestamp of the last bookable day (00:00 of that day)
     * or null if the resource has no booking end date.
     *
     * @param ResourceBookingResourceModel|array<string, mixed>|null $resource
     */
    public static function getBookableUntil(ResourceBookingResourceModel|array|null $resource): int|null
    {
        $row = self::toArray($resource);

        if (empty($row['addBookableUntil']) || !is_numeric($row['bookableUntil'] ?? null)) {
            return null;
        }

        return (int) $row['bookableUntil'];
    }

    /**
     * Returns the number of weeks a user can navigate back from the current week.
     *
     * @param ResourceBookingResourceModel|array<string, mixed>|null $resource
     */
    public static function getWeeksBack(ResourceBookingResourceModel|array|null $resource): int
    {
        $row = self::toArray($resource);

        if (!is_numeric($row['weeksBack'] ?? null)) {
            return RbbConfig::RBB_DEFAULT_WEEKS_BACK;
        }

        return max(0, (int) $row['weeksBack']);
    }

    /**
     * Returns the number of weeks a user can navigate and book ahead from the current week.
     *
     * @param ResourceBookingResourceModel|array<string, mixed>|null $resource
     */
    public static function getWeeksAhead(ResourceBookingResourceModel|array|null $resource): int
    {
        $row = self::toArray($resource);

        if (!is_numeric($row['weeksAhead'] ?? null)) {
            return RbbConfig::RBB_DEFAULT_WEEKS_AHEAD;
        }

        return max(0, (int) $row['weeksAhead']);
    }

    /**
     * Returns true if new bookings of the resource are confirmed automatically (default: true).
     *
     * @param ResourceBookingResourceModel|array<string, mixed>|null $resource
     */
    public static function isAutoConfirmEnabled(ResourceBookingResourceModel|array|null $resource): bool
    {
        $row = self::toArray($resource);

        if (!\array_key_exists('autoConfirm', $row) || null === $row['autoConfirm']) {
            return true;
        }

        return (bool) $row['autoConfirm'];
    }

    /**
     * Converts a time into seconds since midnight. The time is either a string
     * "hh:mm" (00:00 - 24:00) or a Contao time value (timestamp of 1970-01-01
     * in the local time zone, as stored by fields with rgxp "time").
     */
    public static function timeToSeconds(mixed $time): int|null
    {
        if (null === $time || '' === $time) {
            return null;
        }

        if (\is_int($time) || (\is_string($time) && preg_match('/^-?\d+$/', $time))) {
            $time = date('H:i', (int) $time);
        }

        if (!\is_string($time) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return null;
        }

        $seconds = (int) $matches[1] * 3600 + (int) $matches[2] * 60;

        return $seconds <= 86400 ? $seconds : null;
    }

    /**
     * @param ResourceBookingResourceModel|array<string, mixed>|null $resource
     *
     * @return array<string, mixed>
     */
    private static function toArray(ResourceBookingResourceModel|array|null $resource): array
    {
        if (null === $resource) {
            return [];
        }

        if ($resource instanceof ResourceBookingResourceModel) {
            return [
                'weeksBack' => $resource->weeksBack,
                'weeksAhead' => $resource->weeksAhead,
                'restrictWeekdays' => $resource->restrictWeekdays,
                'allowedWeekdays' => $resource->allowedWeekdays,
                'addBookableUntil' => $resource->addBookableUntil,
                'bookableUntil' => $resource->bookableUntil,
                'autoConfirm' => $resource->autoConfirm,
            ];
        }

        return $resource;
    }
}
