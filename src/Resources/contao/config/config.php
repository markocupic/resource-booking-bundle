<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Back end modules
 */
$GLOBALS['BE_MOD']['resourceBookingTool'] = [
    'resource'     => [
        'tables' => ['tl_resource_booking_resource_type', 'tl_resource_booking_resource', 'tl_resource_booking'],
    ],
    'timeSlotType' => [
        'tables' => ['tl_resource_booking_time_slot_type', 'tl_resource_booking_time_slot'],
    ]
];

// Asset path
define('MOD_RESOURCE_BOOKING_ASSET_PATH', 'bundles/markocupicresourcebooking');

// CSS
if (TL_MODE === 'BE')
{
    $GLOBALS['TL_CSS'][] = MOD_RESOURCE_BOOKING_ASSET_PATH . '/css/backend.css|static';
}

// Set backWeeks and aheadWeeks
Contao\Config::set('rbb_intBackWeeks', -27);
Contao\Config::set('rbb_intAheadWeeks', 51);

// Hooks
$GLOBALS['TL_HOOKS']['addCustomRegexp'][] = ['Markocupic\ResourceBookingBundle\RegexpHook', 'customRegexp'];
$GLOBALS['TL_HOOKS']['replaceInsertTags'][] = ['Markocupic\ResourceBookingBundle\ReplaceInsertTagsHook', 'replaceInsertTags'];

// Cron jobs
$GLOBALS['TL_CRON']['daily']['rbb_deleteOldBookings'] = ['Markocupic\ResourceBookingBundle\Cron', 'deleteOldBookingsFromDb'];
