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

class SlotCollection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    private int $index = -1;

    private array $slots = [];

    public function __construct(array $slots = [])
    {
        $slots = array_values($slots);

        foreach ($slots as $slot) {
            if (!$slot instanceof SlotInterface) {
                throw new \InvalidArgumentException('Invalid type: '.get_debug_type($slot));
            }
        }
        $this->slots = $slots;
    }

    public function __get(string $key): mixed
    {
        return $this->current()->$key ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->current()->$key = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->current()->$key);
    }

    public function first(): static
    {
        $this->index = 0;

        return $this;
    }

    public function last(): static
    {
        $this->index = \count($this->slots) - 1;

        return $this;
    }

    public function reset(): static
    {
        $this->index = -1;

        return $this;
    }

    public function next(): static|false
    {
        if (!isset($this->slots[$this->index + 1])) {
            return false;
        }
        ++$this->index;

        return $this;
    }

    public function prev(): static|false
    {
        if ($this->index < 1) {
            return false;
        }
        --$this->index;

        return $this;
    }

    public function current(): SlotInterface
    {
        if ($this->index < 0) {
            $this->first();
        }

        return $this->slots[$this->index];
    }

    public function setRow(array $data): static
    {
        $this->current()->setRow($data);

        return $this;
    }

    public function row(): array
    {
        return $this->current()->row();
    }

    public function delete(): void
    {
        unset($this->slots[max($this->index, 0)]);
        $this->slots = array_values($this->slots);
    }

    public function getSlots(): array
    {
        return $this->slots;
    }

    public function count(): int
    {
        return \count($this->slots);
    }

    public function fetchEach(string $key): array
    {
        return array_map(static fn (SlotInterface $slot) => $slot->$key, $this->slots);
    }

    public function fetchAll(): array
    {
        return array_map(static fn (SlotInterface $slot) => $slot->row(), $this->slots);
    }

    public function sortBy(string $key): self
    {
        foreach ($this->slots as $slot) {
            if (empty((string) $slot->{$key})) {
                throw new \InvalidArgumentException(\sprintf("Cannot sort collection: '%s' has an empty value.", $key));
            }
        }
        $slots = $this->slots;
        usort($slots, static fn ($a, $b) => (string) $a->$key <=> (string) $b->$key);

        return new self($slots);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->slots[$offset]);
    }

    public function offsetGet(mixed $offset): SlotInterface|null
    {
        return $this->slots[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('This collection is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('This collection is immutable.');
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->slots);
    }
}
