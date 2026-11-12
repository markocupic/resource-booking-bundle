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

use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/*
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes'][ResourceBookingWeekcalendarController::TYPE] = '
{title_legend},name,headline,type;
{config_legend},resourceBooking_appConfig,resourceBooking_resourceTypes,resourceBooking_displayClientPersonalData,resourceBooking_displayBookingDetails;
{template_legend:hide},customTpl;
{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space
';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_displayClientPersonalData';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_displayBookingDetails';

/*
 * Add subpalettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_displayClientPersonalData'] = 'resourceBooking_clientPersonalData';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_displayBookingDetails'] = 'resourceBooking_bookingDetailFields';

/*
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_resourceTypes'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['multiple' => true, 'tl_class' => 'clr'],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_displayClientPersonalData'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'boolean', 'default' => true],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_clientPersonalData'] = [
    'exclude'   => true,
    'reference' => &$GLOBALS['TL_LANG']['tl_member'],
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'string', 'length' => 1024, 'notnull' => true, 'default' => 'a:2:{i:0;s:9:\"firstname\";i:1;s:8:\"lastname\";}'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_displayBookingDetails'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'boolean', 'default' => true],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_bookingDetailFields'] = [
    'exclude'   => true,
    'reference' => &$GLOBALS['TL_LANG']['tl_resource_booking'],
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'string', 'length' => 1024, 'notnull' => true, 'default' => 'a:2:{i:0;s:5:\"title\";i:1;s:11:\"description\";}'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_appConfig'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'multiple' => false, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'string', 'length' => 64, 'notnull' => true, 'default' => 'default'],
];
