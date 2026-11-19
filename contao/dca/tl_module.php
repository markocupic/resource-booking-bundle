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
{template_legend:hide},customTpl,resourceBooking_colorScheme;
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
    'eval'      => ['multiple' => true, 'csv' => ',', 'tl_class' => 'clr'],
    'sql'       => ['type' => 'text', 'notnull' => false],
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
    'eval'      => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'csv' => ',', 'tl_class' => 'clr'],
    'sql'       => ['type' => 'string', 'length' => 1024, 'notnull' => true, 'default' => 'firstname,lastname'],
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
    'eval'      => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'csv' => ',', 'tl_class' => 'clr'],
    'sql'       => ['type' => 'string', 'length' => 1024, 'notnull' => true, 'default' => 'title,description'],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_appConfig'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'multiple' => false, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'string', 'length' => 64, 'notnull' => true, 'default' => 'default'],
];

// Color scheme of the week calendar: '' = automatic (OS preference or data-bs-theme of the page), light or dark
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_colorScheme'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['light', 'dark'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_colorSchemeOptions'],
    'eval'      => ['includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_colorSchemeOptions']['auto'], 'tl_class' => 'w50'],
    'sql'       => ['type' => 'string', 'length' => 8, 'notnull' => true, 'default' => ''],
];
