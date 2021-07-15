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
    private $slot;

    public function __construct(Slot $slot)
    {
        $this->slot = $slot;
    }

    public function get(ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1)
    {
        return $this->slot->create($resource, $startTime, $endTime, $desiredItems);
    }
}
