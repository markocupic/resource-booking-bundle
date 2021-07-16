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

use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;

class SlotFactory
{
    private $slotMain;
    private $slotBooking;

    public function __construct(SlotMain $slotMain, SlotBooking $slotBooking)
    {
        $this->slotMain = $slotMain;
        $this->slotBooking = $slotBooking;
    }

    public function get(string $mode, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1)
    {
        if($mode === 'main-window'){
            return $this->slotMain->create($resource, $startTime, $endTime, $desiredItems);
        }
        
        if($mode === 'booking-window'){
            return $this->slotBooking->create($resource, $startTime, $endTime, $desiredItems);
        }
    }
}
