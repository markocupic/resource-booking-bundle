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

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/*
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes'][ResourceBookingWeekcalendarController::TYPE] = '
{title_legend},name,headline,type;
{config_legend},resourceBooking_appConfig,resourceBooking_resourceTypes,resourceBooking_hideDays,resourceBooking_addDateStop,resourceBooking_displayClientPersonalData,resourceBooking_setBookingSubmittedFields;
{template_legend:hide},customTpl;
{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space
';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_hideDays';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_addDateStop';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_displayClientPersonalData';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_setBookingSubmittedFields';

/*
 * Add subpalettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_hideDays'] = 'resourceBooking_hideDaysSelection';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_addDateStop'] = 'resourceBooking_dateStop';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_displayClientPersonalData'] = 'resourceBooking_clientPersonalData';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_setBookingSubmittedFields'] = 'resourceBooking_bookingSubmittedFields';

/*
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_resourceTypes'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['multiple' => true, 'tl_class' => 'clr'],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDays'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDaysSelection'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => RbbConfig::RBB_WEEKDAYS,
    'reference' => &$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'],
    'eval'      => ['multiple' => true, 'tl_class' => 'clr'],
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_addDateStop'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = [
    'exclude'   => true,
    'default'   => time(),
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "varchar(11) NOT NULL default ''",
];
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = [
    'exclude'   => true,
    'default'   => time(),
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "varchar(11) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_displayClientPersonalData'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_clientPersonalData'] = [
    'exclude'   => true,
    'reference' => &$GLOBALS['TL_LANG']['tl_member'],
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(1024) NOT NULL default 'a:2:{i:0;s:9:\"firstname\";i:1;s:8:\"lastname\";}'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_setBookingSubmittedFields'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_bookingSubmittedFields'] = [
    'exclude'   => true,
    'reference' => &$GLOBALS['TL_LANG']['tl_resource_booking'],
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'],
    'sql'       => "varchar(1024) NOT NULL default 'a:2:{i:0;s:5:\"title\";i:1;s:11:\"description\";}'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_appConfig'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'eval'      => ['mandatory' => true, 'multiple' => false, 'tl_class' => 'clr'],
    'sql'       => "varchar(64) NOT NULL default 'default'",
];
