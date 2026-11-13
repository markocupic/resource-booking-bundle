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
 * (tl_resource_booking_time_slot.weekdays).
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
