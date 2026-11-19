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

namespace Markocupic\ResourceBookingBundle\Tests\Slot;

use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;
use Markocupic\ResourceBookingBundle\Slot\Slot;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use PHPUnit\Framework\TestCase;

class SlotCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $collection = new SlotCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
        $this->assertSame([], $collection->all());
    }

    public function testIterationYieldsSlotsInOrder(): void
    {
        $a = $this->slot(100);
        $b = $this->slot(200);
        $collection = new SlotCollection($a, $b);

        $this->assertFalse($collection->isEmpty());
        $this->assertCount(2, $collection);
        $this->assertSame([$a, $b], iterator_to_array($collection));
        $this->assertSame([$a, $b], $collection->all());
    }

    public function testListOfSlotsCanBeUnpacked(): void
    {
        $slots = [5 => $this->slot(100), 9 => $this->slot(200)];

        // Keys are not kept: all() is a list
        $this->assertSame(array_values($slots), (new SlotCollection(...array_values($slots)))->all());
    }

    public function testSortedByStartTimeReturnsANewCollectionAndLeavesTheOriginalUntouched(): void
    {
        $original = new SlotCollection($this->slot(300), $this->slot(100), $this->slot(200));
        $sorted = $original->sortedByStartTime();

        $this->assertNotSame($original, $sorted);
        $this->assertSame([100, 200, 300], $this->startTimes($sorted));
        $this->assertSame([300, 100, 200], $this->startTimes($original));
    }

    public function testSortedByStartTimeComparesNumerically(): void
    {
        // A string comparison would sort "1000" before "999"
        $sorted = (new SlotCollection($this->slot(1000), $this->slot(999)))->sortedByStartTime();

        $this->assertSame([999, 1000], $this->startTimes($sorted));
    }

    public function testMapReturnsANewCollection(): void
    {
        $original = new SlotCollection($this->slot(100), $this->slot(200));
        $mapped = $original->map(static fn (Slot $slot): Slot => $slot->withBookingData(['startTime' => $slot->startTime]));

        $this->assertNotSame($original, $mapped);
        $this->assertSame([['startTime' => 100], ['startTime' => 200]], array_map(static fn (Slot $slot): array => $slot->bookingData, $mapped->all()));
        $this->assertSame([[], []], array_map(static fn (Slot $slot): array => $slot->bookingData, $original->all()));
    }

    /**
     * @return list<int>
     */
    private function startTimes(SlotCollection $collection): array
    {
        return array_map(static fn (Slot $slot): int => $slot->startTime, $collection->all());
    }

    private function slot(int $startTime): Slot
    {
        return new Slot(timeSlotId: 1, resource: new ResourceRules(), startTime: $startTime, endTime: $startTime + 60, now: 0);
    }
}
