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

interface SlotInterface
{

    public function create(ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1, ?int $bookingRepeatStopWeekTstamp = null) :self;

}
