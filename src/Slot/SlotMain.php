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
 * Use SlotFactory to create a Slot instance.
 */
class SlotMain extends AbstractSlot
{
    public const MODE = 'week-calendar';

    public function setIndex(int $index): AbstractSlot
    {
        $this->arrData['index'] = $index;

        return $this;
    }

    public function setBookingCheckboxId(string $value): AbstractSlot
    {
        $this->arrData['bookingCheckboxId'] = $value;

        return $this;
    }

    public function setBookingCheckboxValue(string $value): AbstractSlot
    {
        $this->arrData['bookingCheckboxValue'] = $value;

        return $this;
    }

    /**
     * Check, if slot is bookable.
     *
     * @throws \Exception
     */
    public function isBookable(): bool
    {
        if ($this->isBlocked) {
            return false;
        }

        if (!$this->isWithinAllowedDateRange()) {
            return false;
        }

        $itemsBooked = 0;

        foreach ($this->getBookings() as $booking) {
            // Any items already booked by the currently logged‑in user
            // should not be counted towards the total
            // because the new booking will overwrite the existing one.
            // Complicated, I know. ;-)
            if ($this->isOwnedByLoggedUser($booking)) {
                continue;
            }

            $itemsBooked += (int) $booking['itemsBooked'] ?? 1;
        }

        if ((int) $this->arrData['resource']['itemsAvailable'] > $itemsBooked) {
            return true;
        }

        return false;
    }
}
