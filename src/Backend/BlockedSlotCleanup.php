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

namespace Markocupic\ResourceBookingBundle\Backend;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;

/**
 * Keeps the blocked periods in the mode "slots" consistent when resources or
 * time slots are deleted (see the ondelete callbacks of the resources, the
 * time slots and the timetables).
 */
class BlockedSlotCleanup
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Deletes the blocked periods in the mode "slots" of the resources.
     *
     * @param list<int> $resourceIds
     *
     * @return int Number of deleted blocked periods
     *
     * @throws Exception
     */
    public function deleteForResources(array $resourceIds): int
    {
        $resourceIds = array_values(array_filter(array_map('intval', $resourceIds)));

        if (empty($resourceIds)) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            'DELETE FROM tl_resource_booking_blocked_period WHERE mode = ? AND resource IN (?)',
            [ResourceBookingBlockedPeriodModel::MODE_SLOTS, $resourceIds],
            [ParameterType::STRING, ArrayParameterType::INTEGER],
        );
    }

    /**
     * Removes the time slots from the blocked periods in the mode "slots". A blocked
     * period without remaining slots is kept (it may be reused later).
     *
     * @param list<int> $timeSlotIds
     *
     * @return int Number of updated blocked periods
     *
     * @throws Exception
     */
    public function removeTimeSlots(array $timeSlotIds): int
    {
        $timeSlotIds = array_values(array_filter(array_map('intval', $timeSlotIds)));
        $updated = 0;

        if (empty($timeSlotIds)) {
            return 0;
        }

        $periods = $this->connection->fetchAllAssociative(
            'SELECT id, tstamp, blockedSlots FROM tl_resource_booking_blocked_period WHERE mode = ?',
            [ResourceBookingBlockedPeriodModel::MODE_SLOTS],
        );

        foreach ($periods as $period) {
            $slots = BlockedSlots::fromPeriod($period);
            $remaining = array_values(array_filter($slots, static fn (array $slot): bool => !\in_array($slot['timeSlot'], $timeSlotIds, true)));

            if (\count($remaining) === \count($slots)) {
                continue;
            }

            $set = ['tstamp' => time(), 'blockedSlots' => serialize($remaining)];

            // Without remaining slots the date range is kept
            if (null !== ($dateRange = BlockedSlots::getDateRange($remaining))) {
                [$set['dateFrom'], $set['dateTo']] = $dateRange;
            }

            $this->connection->update('tl_resource_booking_blocked_period', $set, ['id' => (int) $period['id']]);

            ++$updated;
        }

        return $updated;
    }

    /**
     * Why a blocked period in the mode "slots" no longer blocks anything, or null:
     * "resource" (the resource does not exist anymore) or "timeSlots" (none of the
     * time slots belongs to the timetable of the resource anymore).
     *
     * @param array<string, mixed> $period
     *
     * @throws Exception
     */
    public function getOrphanReason(array $period): string|null
    {
        if (ResourceBookingBlockedPeriodModel::MODE_SLOTS !== ($period['mode'] ?? null)) {
            return null;
        }

        $timeSlotType = $this->connection->fetchOne('SELECT timeSlotType FROM tl_resource_booking_resource WHERE id = ?', [(int) ($period['resource'] ?? 0)]);

        if (false === $timeSlotType) {
            return 'resource';
        }

        $slots = BlockedSlots::fromPeriod($period);

        // A blocked period without slots is not orphaned, it may be reused later
        if (empty($slots)) {
            return null;
        }

        $timeSlotIds = array_map('intval', $this->connection->fetchFirstColumn('SELECT id FROM tl_resource_booking_time_slot WHERE pid = ?', [(int) $timeSlotType]));

        foreach ($slots as $slot) {
            if (\in_array($slot['timeSlot'], $timeSlotIds, true)) {
                return null;
            }
        }

        return 'timeSlots';
    }
}
