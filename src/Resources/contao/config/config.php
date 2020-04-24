<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Register back end modules
$GLOBALS['BE_MOD']['resourceBookingTool'] = [
    'resource'     => [
        'tables' => ['tl_resource_booking_resource_type', 'tl_resource_booking_resource', 'tl_resource_booking'],
        'stylesheet' => [\Markocupic\ResourceBookingBundle\Config\Config::MOD_RESOURCE_BOOKING_ASSET_PATH . '/css/backend.css'],
    ],
    'timeSlotType' => [
        'tables' => ['tl_resource_booking_time_slot_type', 'tl_resource_booking_time_slot'],
    ]
];

// Register contao models
$GLOBALS['TL_MODELS']['tl_resource_booking'] = \Markocupic\ResourceBookingBundle\Model\ResourceBookingModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_resource'] = \Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_resource_type'] = \Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_time_slot'] = \Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_time_slot_type'] = \Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel::class;

// Set backWeeks and aheadWeeks
\Contao\Config::set('rbb_intBackWeeks', -10);
\Contao\Config::set('rbb_intAheadWeeks', 51);

