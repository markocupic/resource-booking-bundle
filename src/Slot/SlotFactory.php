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
    /**
     * @var SlotMain
     */
    private $slotMain;

    /**
     * @var SlotBooking
     */
    private $slotBooking;

    public function __construct(SlotMain $slotMain, SlotBooking $slotBooking)
    {
        $this->slotMain = $slotMain;
        $this->slotBooking = $slotBooking;
    }

    public function get(string $mode, ResourceBookingResourceModel $resource, int $startTimestamp, int $endTime, int $desiredItems = 1, ?int $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        if (SlotMain::MODE === $mode) {
            return $this->slotMain->create($resource, $startTimestamp, $endTime, $desiredItems, $bookingRepeatStopWeekTstamp);
        }

        if (SlotBooking::MODE === $mode) {
            return $this->slotBooking->create($resource, $startTimestamp, $endTime, $desiredItems, $bookingRepeatStopWeekTstamp);
        }
    }
}
