<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/**
 * Backend modules
 */
$GLOBALS['TL_LANG']['MOD']['resourceBookingTool'] = 'Resource booking tool';
$GLOBALS['TL_LANG']['MOD']['resource'] = ['Resources', 'Manage resources'];
$GLOBALS['TL_LANG']['MOD']['timeSlotType'] = ['Timetables', 'Manage timetables'];

/**
 * Frontend modules
 */
$GLOBALS['TL_LANG']['FMD']['resourceBooking'] = 'Resource booking modules';
$GLOBALS['TL_LANG']['FMD'][ResourceBookingWeekcalendarController::TYPE] = ['Resource booking module', 'Resource booking weekcalendar'];
