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

// Fields
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_resourceTypes'] = ['Resource types', 'Select at least 1 resource type'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_displayClientPersonalData'] = ['Display the name of the booking person', 'Displays the name and the information selected below of the booking person for bookings of other members. Attention: this data is visible to all logged-in members. Without this option "Anonymous" is displayed; every member always sees their own name.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_clientPersonalData'] = ['Information about the booking person', 'Select the information all logged-in members may see. For the name, select first and last name. The fields available here are defined in the bundle configuration (displayable_member_fields). Fields other than the name have to be added to the template.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_displayBookingDetails'] = ['Display further information about the booking', 'Displays the information selected below for bookings, e.g. the description. Attention: this data is visible to all logged-in members.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_bookingDetailFields'] = ['Information about the booking', 'Select the information all logged-in members may see. The fields available here are defined in the bundle configuration (displayable_booking_fields). The template displays the description by default; further fields have to be added to the template.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_appConfig'] = ['Select app configuration', 'Create your custom app configurations in "config/config.yaml".'];
