<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Config;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;

// Register back end modules
$GLOBALS['BE_MOD']['resourceBookingTool'] = array(
	'resource'     => array(
		'tables' => array('tl_resource_booking_resource_type', 'tl_resource_booking_resource', 'tl_resource_booking'),
	),
	'timeSlotType' => array(
		'tables' => array('tl_resource_booking_time_slot_type', 'tl_resource_booking_time_slot'),
	)
);

// Register contao models
$GLOBALS['TL_MODELS']['tl_resource_booking'] = ResourceBookingModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_resource'] = ResourceBookingResourceModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_resource_type'] = ResourceBookingResourceTypeModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_time_slot'] = ResourceBookingTimeSlotModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_time_slot_type'] = ResourceBookingTimeSlotTypeModel::class;

// Backend Stylesheets
if (TL_MODE === 'BE')
{
	$GLOBALS['TL_CSS'][] = RbbConfig::RBB_ASSET_PATH . '/css/backend.css';
}

