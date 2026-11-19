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

namespace Markocupic\ResourceBookingBundle\Restriction;

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Util\CsvList;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;

/**
 * The rules of the timetables (tl_resource_booking_time_slot_type) and their
 * time slots (tl_resource_booking_time_slot): the first day of the week, the
 * weekdays of a time slot and whether a slot is a valid slot of a resource.
 *
 * The methods accept the model or its row, because the week calendar works with rows.
 */
final class TimeSlotRules
{
    private function __construct()
    {
    }

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
     * (english weekday name, e.g. "monday").
     *
     * A time slot without weekdays (NULL or an empty selection, e.g. a record
     * that has not been migrated yet) is available on every weekday.
     *
     * @param ResourceBookingTimeSlotModel|array<string, mixed>|null $timeSlot
     */
    public static function isAvailableOnWeekday(ResourceBookingTimeSlotModel|array|null $timeSlot, string $weekday): bool
    {
        if (null === $timeSlot) {
            return true;
        }

        $weekdays = $timeSlot instanceof ResourceBookingTimeSlotModel ? $timeSlot->weekdays : ($timeSlot['weekdays'] ?? null);
        $weekdays = CsvList::parse($weekdays);

        if (empty($weekdays)) {
            return true;
        }

        return \in_array(strtolower($weekday), $weekdays, true);
    }

    /**
     * Returns true if a slot from $startTime to $endTime is a valid slot of the resource:
     *
     *  - the time slot exists and is published,
     *  - its timetable exists and is published,
     *  - the timetable is the booking timetable of the resource and
     *  - start and end time match the times of the time slot on that day.
     *
     * This prevents bookings of hidden time slots or of arbitrary times via manipulated requests.
     *
     * @param array<string, mixed>|null $timeSlot     Row of tl_resource_booking_time_slot
     * @param array<string, mixed>|null $timeSlotType Row of tl_resource_booking_time_slot_type
     */
    public static function isValidTimeSlot(array|null $timeSlot, array|null $timeSlotType, ResourceRules $resource, int $startTime, int $endTime): bool
    {
        if (null === $timeSlot || null === $timeSlotType) {
            return false;
        }

        if (empty($timeSlot['published']) || empty($timeSlotType['published'])) {
            return false;
        }

        $timeSlotTypeId = (int) ($timeSlotType['id'] ?? 0);

        if ($timeSlotTypeId < 1 || (int) ($timeSlot['pid'] ?? 0) !== $timeSlotTypeId || $resource->timeSlotTypeId !== $timeSlotTypeId) {
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
}
