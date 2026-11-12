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

namespace Markocupic\ResourceBookingBundle\Config;

class RbbConfig
{
    public const RBB_ASSET_PATH = 'bundles/markocupicresourcebooking';

    public const RBB_WEEKDAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * Default number of weeks a user can navigate back from the current week
     * (tl_resource_booking_resource.weeksBack).
     */
    public const RBB_DEFAULT_WEEKS_BACK = 10;

    /**
     * Default number of weeks a user can navigate and book ahead from the current week
     * (tl_resource_booking_resource.weeksAhead).
     */
    public const RBB_DEFAULT_WEEKS_AHEAD = 52;

    /**
     * Form fields of the booking form that are always permitted. Custom fields
     * have to be registered in markocupic_resource_booking.apps.<name>.permitted_form_fields.
     */
    public const RBB_BASE_PERMITTED_FORM_FIELDS = ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp'];
}
