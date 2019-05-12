<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Back end modules
 */
$GLOBALS['BE_MOD']['resourceBooking'] = array(
    'resourceType' => array
    (
        'tables' => array('tl_resource_booking_resource_type'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList')
    ),
    'resource'     => array
    (
        'tables' => array('tl_resource_booking_resource'),
        'table'  => array('TableWizard', 'importTable'),
        'list'   => array('ListWizard', 'importList')
    ),
    'timeSlotType' => array
    (
        'tables' => array('tl_resource_booking_time_slot_type', 'tl_resource_booking_time_slot'),
    ),
    'bookings'     => array
    (
        'tables' => array('tl_resource_booking'),
    )
);

/**
 * Front end modules
 */
array_insert($GLOBALS['FE_MOD'], 2, array
(
    'resourceBooking' => array
    (
        'resourceBookingWeekCalendar' => 'Markocupic\ResourceBookingBundle\ModuleWeekcalendar',
    )
));

// Asset path
define('MOD_RESOURCE_BOOKING_ASSET_PATH', 'bundles/markocupicresourcebooking');

// CSS
if (TL_MODE == 'BE')
{
    $GLOBALS['TL_CSS'][] = MOD_RESOURCE_BOOKING_ASSET_PATH . '/css/backend.css|static';
}

// Set backWeeks and aheadWeeks
\Contao\Config::set('rbb_intBackWeeks', -27);
\Contao\Config::set('rbb_intAheadWeeks', 51);

// Hooks
$GLOBALS['TL_HOOKS']['addCustomRegexp'][] = array('Markocupic\ResourceBookingBundle\RegexpHook', 'customRegexp');

// Cron jobs
$GLOBALS['TL_CRON']['daily']['rbb_deleteOldBookings'] = array('Markocupic\ResourceBookingBundle\Cron', 'deleteOldBookingsFromDb');
