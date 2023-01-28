<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Slot;

class SlotCollection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    protected int $intIndex = -1;

    protected array $arrSlots = [];

    public function __construct(array $arrSlots = [])
    {
        $arrSlots = array_values($arrSlots);

        foreach ($arrSlots as $objSlot) {
            if (!$objSlot instanceof SlotInterface) {
                throw new \InvalidArgumentException('Invalid type: '.\gettype($objSlot));
            }
        }

        $this->arrSlots = $arrSlots;
    }

    /**
     * Set an object property.
     *
     * @param string $strKey   The property name
     * @param mixed  $varValue The property value
     */
    public function __set(string $strKey, mixed $varValue): void
    {
        if ($this->intIndex < 0) {
            $this->first();
        }

        $this->arrSlots[$this->intIndex]->$strKey = $varValue;
    }

    /**
     * Return an object property.
     *
     * @param string $strKey The property name
     *
     * @return mixed|null The property value or null
     */
    public function __get(string $strKey)
    {
        if ($this->intIndex < 0) {
            $this->first();
        }

        return $this->arrSlots[$this->intIndex]->$strKey ?? null;
    }

    /**
     * Check whether a property is set.
     *
     * @param string $strKey The property name
     *
     * @return bool True if the property is set
     */
    public function __isset(string $strKey): bool
    {
        if ($this->intIndex < 0) {
            $this->first();
        }

        return isset($this->arrSlots[$this->intIndex]->$strKey);
    }

    /**
     * Return the current row as associative array.
     *
     * @return array The current row as array
     */
    public function row(): array
    {
        if ($this->intIndex < 0) {
            $this->first();
        }

        return $this->arrSlots[$this->intIndex]->row();
    }

    /**
     * Set the current row from an array.
     *
     * @param array $arrData The row data as array
     *
     * @return static The slot collection object
     */
    public function setRow(array $arrData): self
    {
        if ($this->intIndex < 0) {
            $this->first();
        }

        $this->arrSlots[$this->intIndex]->setRow($arrData);

        return $this;
    }

    /**
     * Delete the current slot.
     */
    public function delete(): void
    {
        if ($this->intIndex < 0) {
            $this->first();
        }
        unset($this->arrSlots[$this->intIndex]);
    }

    /**
     * Return the slots as array.
     */
    public function getSlots(): ?array
    {
        return $this->arrSlots;
    }

    /**
     * Return the number of rows in the result set.
     *
     * @return int The number of rows
     */
    public function count(): int
    {
        return \count($this->arrSlots);
    }

    /**
     * Go to the first row.
     *
     * @return static The slot collection object
     */
    public function first(): self
    {
        $this->intIndex = 0;

        return $this;
    }

    /**
     * Go to the previous row.
     */
    public function prev(): bool|self
    {
        if ($this->intIndex < 1) {
            return false;
        }

        --$this->intIndex;

        return $this;
    }

    /**
     * Return the current slot.
     *
     * @return SlotInterface The model object
     */
    public function current(): SlotInterface
    {
        if ($this->intIndex < 0) {
            $this->first();
        }

        return $this->arrSlots[$this->intIndex];
    }

    /**
     * Go to the next row.
     */
    public function next(): bool|self
    {
        if (!isset($this->arrSlots[$this->intIndex + 1])) {
            return false;
        }

        ++$this->intIndex;

        return $this;
    }

    /**
     * Go to the last row.
     *
     * @return static The slot collection object
     */
    public function last(): self
    {
        $this->intIndex = \count($this->arrSlots) - 1;

        return $this;
    }

    /**
     * Reset the model.
     *
     * @return static The model collection object
     */
    public function reset(): self
    {
        $this->intIndex = -1;

        return $this;
    }

    /**
     * Fetch a column of each row.
     *
     * @param string $strKey The property name
     *
     * @return array An array with all property values
     */
    public function fetchEach(string $strKey): array
    {
        $this->reset();
        $return = [];

        while ($this->next()) {
            $return[] = $this->$strKey;
        }

        return $return;
    }

    /**
     * Fetch all columns of every row.
     *
     * @return array An array with all rows and columns
     */
    public function fetchAll(): array
    {
        $this->reset();
        $return = [];

        while ($this->next()) {
            $return[] = $this->row();
        }

        return $return;
    }

    /**
     * Check whether an offset exists.
     *
     * @param int $offset The offset
     *
     * @return bool True if the offset exists
     */
    public function offsetExists($offset): bool
    {
        return isset($this->arrSlots[$offset]);
    }

    /**
     * Retrieve a particular offset.
     *
     * @param int $offset The offset
     *
     * @return SlotInterface|null The model or null
     */
    public function offsetGet($offset): ?SlotInterface
    {
        return $this->arrSlots[$offset];
    }

    /**
     * Set a particular offset.
     *
     * @throws \RuntimeException The collection is immutable
     */
    public function offsetSet($offset, mixed $value): void
    {
        throw new \RuntimeException('This collection is immutable');
    }

    /**
     * Unset a particular offset.
     *
     * @param int $offset The offset
     *
     * @throws \RuntimeException The collection is immutable
     */
    public function offsetUnset($offset): void
    {
        throw new \RuntimeException('This collection is immutable');
    }

    /**
     * Retrieve the iterator object.
     *
     * @return \ArrayIterator The iterator object
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->arrSlots);
    }

    /**
     * Sort collection by a given key.
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function sortBy(string $strKey): self
    {
        $this->reset();

        $arrSort = [];

        while ($this->next()) {
            $slot = $this->current();

            if (empty((string) $slot->{$strKey})) {
                throw new \Exception('Can not sort collection, because '.$strKey.' has an empty value.');
            }
            $arrSort[$slot->$strKey] = $slot;
        }

        ksort($arrSort);
        $arrNew = [];

        foreach ($arrSort as $v) {
            $arrNew[] = $v;
        }

        return new self($arrNew);
    }
}
