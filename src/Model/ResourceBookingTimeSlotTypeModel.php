<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Model;

/**
 * Class ResourceBookingTimeSlotTypeModel.
 */
class ResourceBookingTimeSlotTypeModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_time_slot_type';
}
