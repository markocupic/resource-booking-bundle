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

use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;

/*
 * Descriptions of the tokens in the auto suggester of the Notification Center
 * (translation key "nc_tokens.<notification type>.<token>").
 * Both notification types share the same tokens.
 */
foreach ([BookingNotificationType::NAME, CancellationNotificationType::NAME] as $type) {
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_email'] = 'E-mail address of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_firstname'] = 'First name of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_lastname'] = 'Last name of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_gender'] = 'Gender of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_phone'] = 'Phone number of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_street'] = 'Street of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_postal'] = 'Postal code of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_city'] = 'City of the booking person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_*'] = 'All fields of the booking person (tl_member).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_details'] = 'List of all booked or cancelled slots (text).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_details_html'] = 'List of all booked or cancelled slots (HTML).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_description'] = 'Comment on the booking.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_datim'] = 'Date and time of the booking or cancellation.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_type_title'] = 'Title of the resource type.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_type_description'] = 'Description of the resource type.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_type_*'] = 'All fields of the resource type (tl_resource_booking_resource_type).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_title'] = 'Title of the resource.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_description'] = 'Description of the resource.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_*'] = 'All fields of the resource (tl_resource_booking_resource).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_*'] = 'All fields of the first booking (tl_resource_booking).';
}
