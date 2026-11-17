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

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Markocupic\ResourceBookingBundle\Util\BlockedSlots;
use PHPUnit\Framework\TestCase;

class BlockedSlotsTest extends TestCase
{
    public function testParsesTheValueOfTheBackEndForm(): void
    {
        $this->assertSame(
            [
                ['timeSlot' => 8, 'date' => '2030-10-08'],
                ['timeSlot' => 7, 'date' => '2030-10-09'],
                ['timeSlot' => 12, 'date' => '2030-10-09'],
            ],
            // Unsorted, duplicates, invalid entries
            BlockedSlots::parse('7:2030-10-09, 8:2030-10-08,12:2030-10-09,7:2030-10-09,foo,0:2030-10-09,7:2030-02-30,7:1000:2000'),
        );

        $this->assertSame([], BlockedSlots::parse(''));
    }

    public function testToStringIsTheInverseOfParse(): void
    {
        $slots = [['timeSlot' => 8, 'date' => '2030-10-09'], ['timeSlot' => 7, 'date' => '2030-10-08']];

        $this->assertSame('7:2030-10-08,8:2030-10-09', BlockedSlots::toString($slots));
        $this->assertSame(BlockedSlots::normalize($slots), BlockedSlots::parse(BlockedSlots::toString($slots)));
        $this->assertSame('7:2030-10-08', BlockedSlots::key($slots[1]));
    }

    public function testReadsTheSlotsOfABlockedPeriod(): void
    {
        $slots = [['timeSlot' => 7, 'date' => '2030-10-08']];

        $this->assertSame($slots, BlockedSlots::fromPeriod(['id' => 1, 'tstamp' => 1, 'blockedSlots' => serialize($slots)]));
        $this->assertSame($slots, BlockedSlots::fromPeriod(['blockedSlots' => $slots]));
        $this->assertSame([], BlockedSlots::fromPeriod(['blockedSlots' => null]));
        $this->assertSame([], BlockedSlots::fromPeriod([]));
    }

    /**
     * The times are not stored: the time slot is blocked on the day, whatever its times are.
     */
    public function testContainsTheTimeSlotOnItsDay(): void
    {
        $slots = [['timeSlot' => 7, 'date' => '2030-10-08']];

        $this->assertTrue(BlockedSlots::contains($slots, 7, strtotime('2030-10-08 08:00')));
        $this->assertTrue(BlockedSlots::contains($slots, 7, strtotime('2030-10-08 14:30')));
        $this->assertFalse(BlockedSlots::contains($slots, 7, strtotime('2030-10-09 08:00')));
        $this->assertFalse(BlockedSlots::contains($slots, 8, strtotime('2030-10-08 08:00')));
        $this->assertFalse(BlockedSlots::contains($slots, 0, strtotime('2030-10-08 08:00')));
        $this->assertFalse(BlockedSlots::contains([], 7, strtotime('2030-10-08 08:00')));
    }

    public function testDateRangeIsTheFirstAndTheLastDay(): void
    {
        $slots = BlockedSlots::normalize([
            ['timeSlot' => 7, 'date' => '2030-10-10'],
            ['timeSlot' => 7, 'date' => '2030-10-08'],
        ]);

        $this->assertSame([strtotime('2030-10-08'), strtotime('2030-10-10')], BlockedSlots::getDateRange($slots));
        $this->assertNull(BlockedSlots::getDateRange([]));
    }
}
