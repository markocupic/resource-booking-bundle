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

namespace Markocupic\ResourceBookingBundle\Tests\Backend;

use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Backend\BlockedSlotCleanup;
use PHPUnit\Framework\TestCase;

class BlockedSlotCleanupTest extends TestCase
{
    public function testDeletesTheBlockedSlotsOfTheResources(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('DELETE FROM tl_resource_booking_blocked_period WHERE mode = ? AND resource IN (?)'), ['slots', [3, 5]])
            ->willReturn(2)
        ;

        $this->assertSame(2, (new BlockedSlotCleanup($connection))->deleteForResources([3, 0, 5]));
    }

    public function testNothingIsDeletedWithoutResources(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeStatement')
        ;

        $this->assertSame(0, (new BlockedSlotCleanup($connection))->deleteForResources([]));
    }

    /**
     * The time slot is removed; a blocked period without remaining slots is kept (empty).
     */
    public function testRemovesDeletedTimeSlots(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                // Only the deleted time slot: kept without slots and with its date range
                ['id' => 1, 'tstamp' => 1, 'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2030-10-08']])],
                // Deleted and other time slot: updated
                ['id' => 2, 'tstamp' => 1, 'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2030-10-08'], ['timeSlot' => 8, 'date' => '2030-10-09']])],
                // Other time slot only: unchanged
                ['id' => 3, 'tstamp' => 1, 'blockedSlots' => serialize([['timeSlot' => 8, 'date' => '2030-10-09']])],
            ])
        ;

        $connection
            ->expects($this->never())
            ->method('delete')
        ;

        $updates = [];

        $connection
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $data, array $criteria) use (&$updates): int {
                    $updates[$criteria['id']] = $data;

                    return 1;
                },
            )
        ;

        $this->assertSame(2, (new BlockedSlotCleanup($connection))->removeTimeSlots([7]));

        $this->assertSame(serialize([]), $updates[1]['blockedSlots']);
        $this->assertArrayNotHasKey('dateFrom', $updates[1]);

        $this->assertSame(serialize([['timeSlot' => 8, 'date' => '2030-10-09']]), $updates[2]['blockedSlots']);
        $this->assertSame(strtotime('2030-10-09'), $updates[2]['dateFrom']);
        $this->assertSame(strtotime('2030-10-09'), $updates[2]['dateTo']);
    }

    public function testDetectsOrphanedBlockedPeriods(): void
    {
        $period = ['mode' => 'slots', 'resource' => 3, 'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2030-10-08']])];

        // Resource deleted
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(false)
        ;

        $this->assertSame('resource', (new BlockedSlotCleanup($connection))->getOrphanReason($period));

        // Time slot not in the timetable of the resource
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(4)
        ;

        $connection
            ->method('fetchFirstColumn')
            ->willReturn([8, 9])
        ;

        $this->assertSame('timeSlots', (new BlockedSlotCleanup($connection))->getOrphanReason($period));

        // OK
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(4)
        ;

        $connection
            ->method('fetchFirstColumn')
            ->willReturn([7, 8])
        ;

        $this->assertNull((new BlockedSlotCleanup($connection))->getOrphanReason($period));

        // Mode "period"
        $this->assertNull((new BlockedSlotCleanup($connection))->getOrphanReason(['mode' => 'period']));

        // Without slots: not orphaned (may be reused later)
        $this->assertNull((new BlockedSlotCleanup($connection))->getOrphanReason(['mode' => 'slots', 'resource' => 3, 'blockedSlots' => null]));
    }
}
