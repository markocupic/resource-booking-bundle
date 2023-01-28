<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;

// Register back end modules
$GLOBALS['BE_MOD']['resourceBookingTool'] = [
    'resource' => [
        'tables' => ['tl_resource_booking_resource_type', 'tl_resource_booking_resource', 'tl_resource_booking'],
    ],
    'timeSlotType' => [
        'tables' => ['tl_resource_booking_time_slot_type', 'tl_resource_booking_time_slot'],
    ],
];

// Register contao models
$GLOBALS['TL_MODELS']['tl_resource_booking'] = ResourceBookingModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_resource'] = ResourceBookingResourceModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_resource_type'] = ResourceBookingResourceTypeModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_time_slot'] = ResourceBookingTimeSlotModel::class;
$GLOBALS['TL_MODELS']['tl_resource_booking_time_slot_type'] = ResourceBookingTimeSlotTypeModel::class;
