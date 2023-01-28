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

/**
 * Use SlotFactory to create a Slot instance.
 */
class SlotMain extends AbstractSlot
{
    public const MODE = 'main-window';

    /**
     * Check, if slot is bookable.
     *
     * @throws \Exception
     */
    public function isBookable(): bool
    {
        if (!$this->isDateInPermittedRange()) {
            return false;
        }

        $itemsBooked = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                if ($this->user && (int) $this->user->id === (int) $objBookings->member) {
                    continue;
                }
                $itemsBooked += (int) $objBookings->itemsBooked;
            }
        }

        if ((int) $this->arrData['resource']->itemsAvailable > $itemsBooked) {
            return true;
        }

        return false;
    }
}
