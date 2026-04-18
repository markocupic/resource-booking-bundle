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

    public function isBookable(): bool
    {
        return !$this->isBlocked && $this->canFulfillRequestedItems();
    }
}
