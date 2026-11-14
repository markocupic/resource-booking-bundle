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
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;

/**
 * Evaluates the booking restrictions configured on a resource
 * (tl_resource_booking_resource.weeksBack/weeksAhead,
 * tl_resource_booking_resource.addBookableUntil/bookableUntil and
 * tl_resource_booking_resource.autoConfirm) and the weekdays of a time slot
 * (tl_resource_booking_time_slot.weekdays), the first day of the week of a
 * timetable (tl_resource_booking_time_slot_type.beginWeek) and the blocked
 * periods (tl_resource_booking_blocked_period).
 *
 * All methods accept either the resource model or its row array, because the
 * slot classes only keep the row.
 */
class ResourceBookingRestriction
{
    /**
     * Returns the weekday on which the week calendar of the timetable starts
     * (tl_resource_booking_time_slot_type.beginWeek), e.g. "monday".
     *
     * Without a timetable or with an invalid value, the week starts on monday.
     *
     * @param ResourceBookingTimeSlotTypeModel|array<string, mixed>|null $timeSlotType
     */
    public static function getBeginWeek(ResourceBookingTimeSlotTypeModel|array|null $timeSlotType): string
    {
        $beginWeek = $timeSlotType instanceof ResourceBookingTimeSlotTypeModel ? $timeSlotType->beginWeek : ($timeSlotType['beginWeek'] ?? null);
        $beginWeek = strtolower(trim((string) $beginWeek));

        return \in_array($beginWeek, RbbConfig::RBB_WEEKDAYS, true) ? $beginWeek : RbbConfig::RBB_DEFAULT_BEGIN_WEEK;
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
     * Returns true if a slot from $startTime to $endTime is a valid slot of the resource:
     *
     *  - the time slot exists and is published,
     *  - its timetable (tl_resource_booking_time_slot_type) exists and is published,
     *  - the timetable is the booking timetable of the resource and
     *  - start and end time match the times of the time slot on that day.
     *
     * This prevents bookings of hidden time slots or of arbitrary times via manipulated requests.
     *
     * @param array<string, mixed>|null $timeSlot     row of tl_resource_booking_time_slot
     * @param array<string, mixed>|null $timeSlotType row of tl_resource_booking_time_slot_type
     * @param array<string, mixed>      $resource     row of tl_resource_booking_resource
     */
    public static function isValidTimeSlot(array|null $timeSlot, array|null $timeSlotType, array $resource, int $startTime, int $endTime): bool
    {
        if (null === $timeSlot || null === $timeSlotType) {
            return false;
        }

        if (empty($timeSlot['published']) || empty($timeSlotType['published'])) {
            return false;
        }

        $timeSlotTypeId = (int) ($timeSlotType['id'] ?? 0);

        if ($timeSlotTypeId < 1 || (int) ($timeSlot['pid'] ?? 0) !== $timeSlotTypeId || (int) ($resource['timeSlotType'] ?? 0) !== $timeSlotTypeId) {
            return false;
        }

        if ($endTime <= $startTime) {
            return false;
        }

        $slotStart = (int) ($timeSlot['startTime'] ?? 0);
        $slotEnd = (int) ($timeSlot['endTime'] ?? 0);

        // The week calendar adds the time of the time slot (seconds) to the midnight of the day.
        $midnight = strtotime(date('Y-m-d', $startTime));

        if ($startTime - $midnight === $slotStart && $endTime - $midnight === $slotEnd) {
            return true;
        }

        // Repetitions are calculated with "+7 days", which keeps the wall clock time.
        // On days with a daylight saving time change, this differs from the seconds since midnight.
        $day = date('Y-m-d', $startTime);

        return $startTime === strtotime($day.' '.UtcTimeHelper::parseStartTime($slotStart))
            && $endTime === strtotime($day.' '.UtcTimeHelper::parseEndTime($slotEnd));
    }

    /**
     * Returns true if the blocked period (row of tl_resource_booking_blocked_period)
     * applies to the resource and covers the slot from $startTime to $endTime.
     *
     * The slot is covered if its day lies between dateFrom and dateTo (inclusive,
     * an empty dateTo means unlimited) and, if the period has blocked times, if the
     * slot overlaps one of the blocked times on its weekday. Without blocked times
     * the whole period is blocked.
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

        if ($dayStart < $dateFrom) {
            return false;
        }

        // A period without end date is unlimited
        if (!empty($period['dateTo']) && $dayStart > strtotime(date('Y-m-d', (int) $period['dateTo']))) {
            return false;
        }

        $blockedTimes = self::getBlockedTimes($period);

        // Without blocked times the whole period is blocked
        if (empty($blockedTimes)) {
            return true;
        }

        $weekday = strtolower(date('l', $startTime));
        $slotStart = $startTime - $dayStart;
        $slotEnd = $endTime - $dayStart;

        foreach ($blockedTimes as $blockedTime) {
            if ('daily' !== $blockedTime['weekday'] && $weekday !== $blockedTime['weekday']) {
                continue;
            }

            if ($slotStart < $blockedTime['timeTo'] && $slotEnd > $blockedTime['timeFrom']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the blocked times of a blocked period (tl_resource_booking_blocked_period.blockedTimes)
     * as a list of ['weekday' => 'daily'|'monday'|..., 'timeFrom' => seconds, 'timeTo' => seconds].
     * An empty time means the start or the end of the day.
     *
     * @param array<string, mixed> $period
     *
     * @return array<int, array{weekday: string, timeFrom: int, timeTo: int}>
     */
    public static function getBlockedTimes(array $period): array
    {
        $blockedTimes = [];

        foreach (StringUtil::deserialize($period['blockedTimes'] ?? null, true) as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $weekday = strtolower((string) ($row['weekday'] ?? ''));

            if ('daily' !== $weekday && !\in_array($weekday, RbbConfig::RBB_WEEKDAYS, true)) {
                continue;
            }

            $blockedTimes[] = [
                'weekday' => $weekday,
                'timeFrom' => self::timeToSeconds($row['timeFrom'] ?? null) ?? 0,
                'timeTo' => self::timeToSeconds($row['timeTo'] ?? null) ?? 86400,
            ];
        }

        return $blockedTimes;
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
                'addBookableUntil' => $resource->addBookableUntil,
                'bookableUntil' => $resource->bookableUntil,
                'autoConfirm' => $resource->autoConfirm,
            ];
        }

        return $resource;
    }
}
