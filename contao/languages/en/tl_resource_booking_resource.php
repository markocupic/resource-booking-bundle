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

// Legends
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['title_legend'] = 'Title settings';
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['booking_restriction_legend'] = 'Booking restrictions';

// Operations
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['bookings'] = 'Watch bookings from resource with ID %s';

// Global operations
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['new'] = ['New', 'Add new resource.'];

// Fields
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['published'] = ['Publish', 'Show the resource in the front end and make it available for booking.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['title'] = ['Title', 'Enter the title of the resource, e.g. "Workshop" or "Projector 1".'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['description'] = ['Description', 'This description is displayed in the week calendar above the table, e.g. for notes on usage.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['timeSlotType'] = ['Booking timetable', 'Select the booking timetable that contains the bookable time slots.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['itemsAvailable'] = ['Available items (Default: 1).', 'Please enter a natural digit larger then 0.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['weeksBack'] = ['Weeks back', 'Specify how many weeks back from the current week the user can navigate (default: 10). If the cron job is enabled, older bookings are deleted; with 0 nothing is deleted.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['weeksAhead'] = ['Weeks ahead', 'Specify how many weeks ahead from the current week the user can navigate and book (default: 52).'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['restrictWeekdays'] = ['Allow bookings only on selected weekdays', 'Specify whether the resource can only be booked on selected weekdays. The other weekdays are hidden in the week calendar.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['allowedWeekdays'] = ['Bookable weekdays', 'Select the weekdays on which the resource can be booked.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['addBookableUntil'] = ['Allow bookings only up to a certain date', 'Specify whether the resource can only be booked up to a certain date.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['autoConfirm'] = ['Confirm bookings automatically', 'New bookings of this resource are confirmed automatically. If disabled, bookings have to be confirmed in the back end.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['bookableUntil'] = ['Bookable until', 'Select the date up to which the resource can be booked (inclusive).'];
