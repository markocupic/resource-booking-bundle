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

namespace Markocupic\ResourceBookingBundle\Slot;

/**
 * The slots of a booking request (selected slots and their repetitions).
 *
 * Immutable: methods that change the collection return a new collection.
 *
 * @implements \IteratorAggregate<int, Slot>
 */
final class SlotCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<Slot>
     */
    private readonly array $slots;

    public function __construct(Slot ...$slots)
    {
        $this->slots = array_values($slots);
    }

    /**
     * Returns a new collection with the slots sorted by start time (ascending).
     */
    public function sortedByStartTime(): self
    {
        $slots = $this->slots;
        usort($slots, static fn (Slot $a, Slot $b): int => (int) $a->startTime <=> (int) $b->startTime);

        return new self(...$slots);
    }

    /**
     * Returns a new collection with the result of the callback for every slot.
     *
     * @param \Closure(Slot): Slot $callback
     */
    public function map(\Closure $callback): self
    {
        return new self(...array_map($callback, $this->slots));
    }

    /**
     * @return list<Slot>
     */
    public function all(): array
    {
        return $this->slots;
    }

    public function isEmpty(): bool
    {
        return [] === $this->slots;
    }

    public function count(): int
    {
        return \count($this->slots);
    }

    /**
     * @return \ArrayIterator<int, Slot>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->slots);
    }
}
