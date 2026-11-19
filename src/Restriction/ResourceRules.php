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
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;

/**
 * The booking rules of a resource (tl_resource_booking_resource): how many weeks
 * the user can navigate back and ahead, the last bookable day, the number of
 * items and whether new bookings are confirmed automatically.
 *
 * Immutable value object, created from the model or from its row.
 */
final class ResourceRules
{
    /**
     * @param int      $resourceTypeId tl_resource_booking_resource.pid
     * @param int      $timeSlotTypeId Booking timetable of the resource
     * @param int|null $bookableUntil  Timestamp of the last bookable day (00:00), null: no end date
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly int $resourceTypeId = 0,
        public readonly int $timeSlotTypeId = 0,
        public readonly int $itemsAvailable = 0,
        public readonly int $weeksBack = RbbConfig::RBB_DEFAULT_WEEKS_BACK,
        public readonly int $weeksAhead = RbbConfig::RBB_DEFAULT_WEEKS_AHEAD,
        public readonly int|null $bookableUntil = null,
        public readonly bool $autoConfirm = true,
    ) {
    }

    /**
     * Without a resource the defaults apply.
     */
    public static function fromModel(ResourceBookingResourceModel|null $resource): self
    {
        if (null === $resource) {
            return new self();
        }

        return self::fromRow([
            'id' => $resource->id,
            'pid' => $resource->pid,
            'timeSlotType' => $resource->timeSlotType,
            'itemsAvailable' => $resource->itemsAvailable,
            'weeksBack' => $resource->weeksBack,
            'weeksAhead' => $resource->weeksAhead,
            'addBookableUntil' => $resource->addBookableUntil,
            'bookableUntil' => $resource->bookableUntil,
            'autoConfirm' => $resource->autoConfirm,
        ]);
    }

    /**
     * Missing or empty values fall back to the defaults: weeksBack and weeksAhead to
     * RbbConfig::RBB_DEFAULT_WEEKS_*, autoConfirm to true, no booking end date.
     *
     * @param array<string, mixed> $row Row of tl_resource_booking_resource
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            resourceTypeId: (int) ($row['pid'] ?? 0),
            timeSlotTypeId: (int) ($row['timeSlotType'] ?? 0),
            itemsAvailable: (int) ($row['itemsAvailable'] ?? 0),
            weeksBack: is_numeric($row['weeksBack'] ?? null) ? max(0, (int) $row['weeksBack']) : RbbConfig::RBB_DEFAULT_WEEKS_BACK,
            weeksAhead: is_numeric($row['weeksAhead'] ?? null) ? max(0, (int) $row['weeksAhead']) : RbbConfig::RBB_DEFAULT_WEEKS_AHEAD,
            bookableUntil: !empty($row['addBookableUntil']) && is_numeric($row['bookableUntil'] ?? null) ? (int) $row['bookableUntil'] : null,
            autoConfirm: null === ($row['autoConfirm'] ?? null) || (bool) $row['autoConfirm'],
        );
    }
}
