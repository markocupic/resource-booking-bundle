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

use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/**
 * Backend modules
 */
$GLOBALS['TL_LANG']['MOD']['resourceBookingTool'] = 'Ressourcen-Buchung';
$GLOBALS['TL_LANG']['MOD']['resource'] = ['Ressourcen', 'Ressourcen verwalten'];
$GLOBALS['TL_LANG']['MOD']['timeSlotType'] = ['Zeitpläne', 'Buchungs-Zeitpläne verwalten'];

/**
 * Frontend modules
 */
$GLOBALS['TL_LANG']['FMD']['resourceBooking'] = 'Ressourcen-Buchungs-Module';
$GLOBALS['TL_LANG']['FMD'][ResourceBookingWeekcalendarController::TYPE] = ['Wochenkalender mit Resourcen-Buchung', 'Wochenkalender mit Resourcen-Buchung'];
