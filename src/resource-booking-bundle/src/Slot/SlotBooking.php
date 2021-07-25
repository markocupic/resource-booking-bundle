<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Slot;

/**
 * Class SlotBooking.
 *
 * Use SlotFactory to create Slot instance
 */
class SlotBooking extends AbstractSlot
{
    public const MODE = 'booking-window';

    /**
     * Check, if slot is bookable.
     */
    public function isBookable(): bool
    {
        $itemsBooked = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                if ($this->user && (int) $this->user->id === (int) $objBookings->member) {
                    continue;
                }
                $itemsBooked += (int) $objBookings->itemsBooked;
            }
        }

        if ((int) $this->arrData['resource']->itemsAvailable >= $itemsBooked + $this->arrData['itemsBooked']) {
            return true;
        }

        return false;
    }
}
