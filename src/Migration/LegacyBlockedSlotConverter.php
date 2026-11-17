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

namespace Markocupic\ResourceBookingBundle\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;

/**
 * Converts blocked slots with start and end time (blocked bookings up to version 5,
 * first development state of the mode "slots") into the format of BlockedSlots:
 * ['timeSlot' => id, 'date' => 'Y-m-d']. Without time slot ID, the time slot of the
 * timetable of the resource with the same start and end time is used.
 */
class LegacyBlockedSlotConverter
{
    /**
     * @var array<int, array<string, int>>
     */
    private array $timeSlotsByTime = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<array<string, mixed>> $slots Entries with timeSlot (optional), startTime and endTime
     *
     * @return list<array{timeSlot: int, date: string}> Slots without matching time slot are skipped
     *
     * @throws Exception
     */
    public function convert(int $resourceId, array $slots): array
    {
        $converted = [];

        foreach ($slots as $slot) {
            // Already converted
            if (isset($slot['date'])) {
                $converted[] = $slot;
                continue;
            }

            $startTime = (int) ($slot['startTime'] ?? 0);
            $endTime = (int) ($slot['endTime'] ?? 0);

            if ($startTime <= 0 || $endTime <= $startTime) {
                continue;
            }

            $timeSlot = (int) ($slot['timeSlot'] ?? 0) ?: $this->findTimeSlot($resourceId, $startTime, $endTime);

            if ($timeSlot > 0) {
                $converted[] = ['timeSlot' => $timeSlot, 'date' => date('Y-m-d', $startTime)];
            }
        }

        return BlockedSlots::normalize($converted);
    }

    /**
     * @throws Exception
     */
    private function findTimeSlot(int $resourceId, int $startTime, int $endTime): int
    {
        if (!isset($this->timeSlotsByTime[$resourceId])) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT t.id, t.startTime, t.endTime FROM tl_resource_booking_time_slot t
                    INNER JOIN tl_resource_booking_resource r ON r.timeSlotType = t.pid
                    WHERE r.id = ?',
                [$resourceId],
            );

            $this->timeSlotsByTime[$resourceId] = [];

            foreach ($rows as $row) {
                $this->timeSlotsByTime[$resourceId][$row['startTime'].':'.$row['endTime']] ??= (int) $row['id'];
            }
        }

        // The week calendar adds the time of the time slot (seconds) to the midnight of the day
        $midnight = (int) strtotime(date('Y-m-d', $startTime));

        return $this->timeSlotsByTime[$resourceId][($startTime - $midnight).':'.($endTime - $midnight)] ?? 0;
    }
}
