<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['resourceBookingWeekcalendar'] = '{title_legend},name,headline,type;{config_legend},resourceBooking_resourceTypes,resourceBooking_hideDays,resourceBooking_intAheadWeek,resourceBooking_addDateStop,resourceBooking_displayClientPersonalData;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_hideDays';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_addDateStop';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_displayClientPersonalData';

// Subpalettes
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_addDateStop'] = 'resourceBooking_dateStop';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_displayClientPersonalData'] = 'resourceBooking_clientPersonalData';

/**
 * Add subpalettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_hideDays'] = 'resourceBooking_hideDaysSelection';

/**
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_resourceTypes'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_resourceTypes'],
    'exclude'          => true,
    'inputType'        => 'checkbox',
    'options_callback' => ['tl_module_resource_booking', 'getResourceTypes'],
    'eval'             => ['multiple' => true, 'tl_class' => 'clr'],
    'sql'              => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDays'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_hideDays'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDaysSelection'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_hideDaysSelection'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => range(0, 6),
    'reference' => &$GLOBALS['TL_LANG']['DAYS_LONG'],
    'eval'      => ['multiple' => true, 'tl_class' => 'clr'],
    'sql'       => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_intAheadWeek'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_intAheadWeek'],
    'exclude'   => true,
    'inputType' => 'text',
    'options'   => range(0, 156),
    'eval'      => ['tl_class' => 'clr', 'rgxp' => 'natural'],
    'sql'       => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_addDateStop'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_addDateStop'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_dateStop'],
    'exclude'   => true,
    'default'   => time(),
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "varchar(11) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_dateStop'],
    'exclude'   => true,
    'default'   => time(),
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "varchar(11) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_displayClientPersonalData'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_displayClientPersonalData'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_clientPersonalData'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_clientPersonalData'],
    'exclude'          => true,
    'reference'        => &$GLOBALS['TL_LANG']['tl_member'],
    'inputType'        => 'select',
    'options_callback' => ['tl_module_resource_booking', 'getTlMemberFields'],
    'eval'             => ['mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'],
    'sql'              => "varchar(1024) NOT NULL default 'a:2:{i:0;s:9:\"firstname\";i:1;s:8:\"lastname\";}'"
];

/**
 * Class tl_module_resource_booking
 */
class tl_module_resource_booking extends Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');
    }

    /**
     * @return array
     */
    public function getResourceTypes(): array
    {
        $opt = [];
        $objDb = Contao\Database::getInstance()->prepare('SELECT * FROM tl_resource_booking_resource_type')->execute();
        while ($objDb->next())
        {
            $opt[$objDb->id] = $objDb->title;
        }
        return $opt;
    }

    /**
     * @return array
     */
    public function getWeekdays(): array
    {
        return range(0, 6);
    }

    /**
     * Options callback
     * @return array
     */
    public function getTlMemberFields(): array
    {
        $arrFieldnames = \Contao\Database::getInstance()->getFieldNames('tl_member');

        \Contao\System::loadLanguageFile('tl_member');
        $arrOpt = [];
        foreach ($arrFieldnames as $fieldname)
        {
            if($fieldname === 'id' || $fieldname === 'password')
            {
                continue;
            }
            $arrOpt[] = $fieldname;
        }

        unset($arrOpt['id']);
        unset($arrOpt['password']);

        return $arrOpt;
    }

}
