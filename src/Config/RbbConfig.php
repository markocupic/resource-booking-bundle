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
     * Weekday on which the week calendar starts if the timetable does not set
     * one (tl_resource_booking_time_slot_type.beginWeek).
     */
    public const RBB_DEFAULT_BEGIN_WEEK = 'monday';

    /**
     * Fields of tl_member that may be selected in the frontend module
     * (tl_module.resourceBooking_clientPersonalData) and are then visible to
     * all logged-in members in the week calendar. Can be changed with
     * markocupic_resource_booking.displayable_member_fields.
     */
    public const RBB_DEFAULT_DISPLAYABLE_MEMBER_FIELDS = ['firstname', 'lastname', 'company', 'city', 'phone'];

    /**
     * Fields of tl_member that are never sent to the front end, whatever is configured.
     */
    public const RBB_FORBIDDEN_MEMBER_FIELDS = ['password', 'secret', 'backupCodes', 'trustedTokenVersion', 'session'];

    /**
     * Fields of tl_resource_booking that may be selected in the frontend module
     * (tl_module.resourceBooking_bookingDetailFields) and are then visible to
     * all logged-in members in the week calendar. Can be changed with
     * markocupic_resource_booking.displayable_booking_fields.
     */
    public const RBB_DEFAULT_DISPLAYABLE_BOOKING_FIELDS = ['title', 'description'];

    /**
     * Fields of tl_resource_booking that are always sent to the front end
     * (under their own name), because the week calendar needs them.
     */
    public const RBB_ALWAYS_SENT_BOOKING_FIELDS = ['id', 'pid', 'itemsBooked', 'bookingTime', 'confirmed'];

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
