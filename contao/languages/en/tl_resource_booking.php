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
$GLOBALS['TL_LANG']['tl_resource_booking']['booking_legend'] = 'Booking settings';
$GLOBALS['TL_LANG']['tl_resource_booking']['module_legend'] = 'Module settings';
$GLOBALS['TL_LANG']['tl_resource_booking']['time_legend'] = 'Time settings';

// Global operations
$GLOBALS['TL_LANG']['tl_resource_booking']['new'] = ['New', 'Add new booking.'];

// Fields
$GLOBALS['TL_LANG']['tl_resource_booking']['pid'] = ['Resource', 'The booked resource.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['timeSlotId'] = ['Time slot', 'The booked time slot from the booking timetable of the resource.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['bookingTime'] = ['Booking time', 'Date and time when the booking was received.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['member'] = ['Booked by frontend user', 'The member (frontend user) who made the booking.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['isBlocked'] = ['The slot is unavailable (e.g. during holiday periods)', 'The time slot is blocked and cannot be booked, e.g. during holiday periods.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['title'] = ['Title', 'The title of the booking. It is generated automatically when booking.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['description'] = ['Description', 'A short description of the booking, e.g. the purpose or the class.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['startTime'] = ['Start time', 'Start of the booked time slot. The value is taken from the time slot.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['endTime'] = ['End time', 'End of the booked time slot. The value is taken from the time slot.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['bookingUuid'] = ['Booking Id', 'The unique identifier of the booking. Repetitions of a booking share the same identifier.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['itemsBooked'] = ['Booked units', 'The number of booked units if several items of the resource are available.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['moduleId'] = ['Frontend module', 'The frontend module through which the booking was made.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['confirmed'] = ['Confirmed', 'The booking is confirmed. If automatic confirmation is disabled for the resource, the booking has to be confirmed here.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['upcoming'] = ['Upcoming reservation', 'The booking lies in the future. Past bookings are marked as expired automatically by a cron job.'];
