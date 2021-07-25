<?php

/*
 * This file is part of Resource Booking Notification Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license LGPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-notification-bundle
 */

// notification_center
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['resource_booking'] = array
(
	'onbooking' => array
	(
		'email_sender_name' => array('booking_person_firstname', 'booking_person_lastname'),
		'email_sender_address' => array('booking_person_email'),
		'recipients'    => array('booking_person_email'),
		'email_replyTo' => array('booking_person_email'),
		'email_recipient_cc' => array('booking_person_email'),
		'email_subject' => array('booking_person_*', 'booking_details', 'booking_*', 'booking_resource_*', 'booking_resource_type_*'),
		'email_text'    => array(
			'booking_person_firstname', 'booking_person_lastname', 'booking_person_*',
			'booking_details', 'booking_description', 'booking_datim', 'booking_*',
			'booking_resource_title', 'booking_resource_description', 'booking_resource_*',
			'booking_resource_type_title', 'booking_resource_type_description', 'booking_resource_type_*'
		),
		'email_html'    => array(
			'booking_person_firstname', 'booking_person_lastname', 'booking_person_*',
			'booking_details_html', 'booking_description', 'booking_datim', 'booking_*',
			'booking_resource_title', 'booking_resource_description', 'booking_resource_*',
			'booking_resource_type_title', 'booking_resource_type_description', 'booking_resource_type_*'
		),
	),

	'oncanceling' => array
	(
		// Field in tl_nc_language
		'email_sender_name' => array('booking_person_firstname', 'booking_person_lastname'),
		'email_sender_address' => array('booking_person_email'),
		'recipients'    => array('booking_person_email'),
		'email_replyTo' => array('booking_person_email'),
		'email_recipient_cc' => array('booking_person_email'),
		'email_subject' => array('booking_person_*', 'booking_details', 'booking_*', 'booking_resource_*', 'booking_resource_type_*'),
		'email_text'    => array(
			'booking_person_firstname', 'booking_person_lastname', 'booking_person_*',
			'booking_details', 'booking_description', 'booking_datim', 'booking_*',
			'booking_resource_title', 'booking_resource_description', 'booking_resource_*',
			'booking_resource_type_title', 'booking_resource_type_description', 'booking_resource_type_*'
		),
		'email_html'    => array(
			'booking_person_firstname', 'booking_person_lastname', 'booking_person_*',
			'booking_details_html', 'booking_description', 'booking_datim', 'booking_*',
			'booking_resource_title', 'booking_resource_description', 'booking_resource_*',
			'booking_resource_type_title', 'booking_resource_type_description', 'booking_resource_type_*'
		),
	),
);
