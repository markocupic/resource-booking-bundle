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
 * Use SlotFactory to create a new Slot instance.
 */
class SlotBooking extends AbstractSlot
{
    public const MODE = 'booking-window';

    public function setTimeSlotId(int $id): AbstractSlot
    {
        $this->arrData['timeSlotId'] = $id;

        return $this;
    }

    /**
     * Check, if the slot is bookable.
     */
    public function isBookable(): bool
    {
        $itemsBooked = 0;

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            if ($this->user && (int) $this->user->id === (int) $booking['member'] ?? -1) {
                $iterator->next();

                continue;
            }

            $itemsBooked += (int) $booking['itemsBooked'];

            $iterator->next();
        }

        if ((int) $this->arrData['resource']['itemsAvailable'] >= $itemsBooked + $this->arrData['itemsBooked']) {
            return true;
        }

        return false;
    }
}
