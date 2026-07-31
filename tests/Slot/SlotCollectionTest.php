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

use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotInterface;
use PHPUnit\Framework\TestCase;

class SlotCollectionTest extends TestCase
{
    public function testConstructorRejectsNonSlotValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SlotCollection([new \stdClass()]);
    }

    public function testConstructorReindexesKeys(): void
    {
        $a = $this->slot(['id' => 1]);
        $b = $this->slot(['id' => 2]);

        $collection = new SlotCollection([5 => $a, 9 => $b]);

        $this->assertSame([0, 1], array_keys($collection->getSlots()));
        $this->assertSame([$a, $b], $collection->getSlots());
    }

    public function testCountReturnsNumberOfSlots(): void
    {
        $this->assertCount(0, new SlotCollection());
        $this->assertCount(3, new SlotCollection([$this->slot(), $this->slot(), $this->slot()]));
    }

    public function testIterationYieldsSlotsInOrder(): void
    {
        $collection = new SlotCollection([
            $this->slot(['id' => 10]),
            $this->slot(['id' => 20]),
            $this->slot(['id' => 30]),
        ]);

        $seen = [];
        $collection->reset();

        while ($collection->next()) {
            $seen[] = $collection->current()->id;
        }

        $this->assertSame([10, 20, 30], $seen);
        // Pointer is exhausted.
        $this->assertFalse($collection->next());
    }

    public function testFirstLastAndCurrent(): void
    {
        $first = $this->slot(['id' => 1]);
        $last = $this->slot(['id' => 3]);

        $collection = new SlotCollection([$first, $this->slot(['id' => 2]), $last]);

        $this->assertSame($first, $collection->first()->current());
        $this->assertSame($last, $collection->last()->current());
    }

    public function testPrevNavigatesBackwardsAndStopsAtStart(): void
    {
        $collection = new SlotCollection([$this->slot(['id' => 1]), $this->slot(['id' => 2])]);

        $collection->last(); // index 1

        $this->assertSame(1, $collection->prev()->current()->id);
        // Already at the first element -> prev() returns false.
        $this->assertFalse($collection->prev());
    }

    public function testMagicAccessorsDelegateToCurrentSlot(): void
    {
        $collection = new SlotCollection([$this->slot(['title' => 'Hello'])]);
        $collection->first();

        $this->assertSame('Hello', $collection->title);
        $this->assertTrue(isset($collection->title));
        $this->assertFalse(isset($collection->missing));

        $collection->title = 'Changed';
        $this->assertSame('Changed', $collection->current()->title);
    }

    public function testFetchEachReturnsTheValuesForAKey(): void
    {
        $collection = new SlotCollection([
            $this->slot(['startTime' => 100]),
            $this->slot(['startTime' => 200]),
        ]);

        $this->assertSame([100, 200], $collection->fetchEach('startTime'));
    }

    public function testFetchAllReturnsTheRows(): void
    {
        $collection = new SlotCollection([
            $this->slot(['id' => 1, 'startTime' => 100]),
            $this->slot(['id' => 2, 'startTime' => 200]),
        ]);

        $this->assertSame(
            [
                ['id' => 1, 'startTime' => 100],
                ['id' => 2, 'startTime' => 200],
            ],
            $collection->fetchAll(),
        );
    }

    public function testSortByReturnsANewSortedCollectionAndLeavesTheOriginalUntouched(): void
    {
        $original = new SlotCollection([
            $this->slot(['startTime' => 300]),
            $this->slot(['startTime' => 100]),
            $this->slot(['startTime' => 200]),
        ]);

        $sorted = $original->sortBy('startTime');

        $this->assertNotSame($original, $sorted);
        $this->assertSame([100, 200, 300], $sorted->fetchEach('startTime'));
        // The original order is preserved (immutability).
        $this->assertSame([300, 100, 200], $original->fetchEach('startTime'));
    }

    public function testSortByThrowsWhenAValueIsEmpty(): void
    {
        $collection = new SlotCollection([
            $this->slot(['startTime' => 100]),
            $this->slot(['startTime' => '']),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $collection->sortBy('startTime');
    }

    public function testSortByAllowsZeroValues(): void
    {
        $collection = new SlotCollection([
            $this->slot(['startTime' => 2]),
            $this->slot(['startTime' => 0]),
            $this->slot(['startTime' => 1]),
        ]);

        // A legitimate 0 must not be treated as "empty".
        $sorted = $collection->sortBy('startTime');

        $this->assertSame([0, 1, 2], $sorted->fetchEach('startTime'));
    }

    public function testOffsetSetIsImmutable(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SlotCollection([$this->slot()]))->offsetSet(0, $this->slot());
    }

    public function testOffsetUnsetIsImmutable(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SlotCollection([$this->slot()]))->offsetUnset(0);
    }

    public function testOffsetGetAndExists(): void
    {
        $slot = $this->slot(['id' => 42]);
        $collection = new SlotCollection([$slot]);

        $this->assertTrue($collection->offsetExists(0));
        $this->assertFalse($collection->offsetExists(1));
        $this->assertSame($slot, $collection->offsetGet(0));
        $this->assertNull($collection->offsetGet(99));
    }

    public function testDeleteRemovesTheCurrentSlotAndReindexes(): void
    {
        $collection = new SlotCollection([
            $this->slot(['id' => 1]),
            $this->slot(['id' => 2]),
            $this->slot(['id' => 3]),
        ]);

        $collection->reset();
        $collection->next(); // points at id 1

        $collection->delete();

        $this->assertCount(2, $collection);
        $this->assertSame([2, 3], $collection->fetchEach('id'));
        $this->assertSame([0, 1], array_keys($collection->getSlots()));
    }

    public function testGetIteratorIteratesAllSlots(): void
    {
        $slots = [$this->slot(['id' => 1]), $this->slot(['id' => 2])];
        $collection = new SlotCollection($slots);

        $this->assertSame($slots, iterator_to_array($collection));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function slot(array $data = []): SlotInterface
    {
        return new class($data) implements SlotInterface {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(private array $data = [])
            {
            }

            public function __get(string $key): mixed
            {
                return $this->data[$key] ?? null;
            }

            public function __set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function __isset(string $key): bool
            {
                return isset($this->data[$key]);
            }

            public function create(int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1, int|null $bookingRepeatStopWeekTstamp = null): SlotInterface
            {
                return $this;
            }

            public function row(): array
            {
                return $this->data;
            }

            public function setRow(array $data): void
            {
                $this->data = $data;
            }
        };
    }
}
