<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Contao\Database;
use Contao\System;

/**
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['resourceBookingWeekcalendar'] = '{title_legend},name,headline,type;{config_legend},resourceBooking_resourceTypes,resourceBooking_hideDays,resourceBooking_intAheadWeek,resourceBooking_addDateStop,resourceBooking_displayClientPersonalData,resourceBooking_setBookingSubmittedFields;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_hideDays';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_addDateStop';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_displayClientPersonalData';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'resourceBooking_setBookingSubmittedFields';

/**
 * Add subpalettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_hideDays'] = 'resourceBooking_hideDaysSelection';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_addDateStop'] = 'resourceBooking_dateStop';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_displayClientPersonalData'] = 'resourceBooking_clientPersonalData';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['resourceBooking_setBookingSubmittedFields'] = 'resourceBooking_bookingSubmittedFields';

/**
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_resourceTypes'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_resourceTypes'],
	'exclude'          => true,
	'inputType'        => 'checkbox',
	'options_callback' => array('tl_module_resource_booking', 'getResourceTypes'),
	'eval'             => array('multiple' => true, 'tl_class' => 'clr'),
	'sql'              => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDays'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_hideDays'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDaysSelection'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_hideDaysSelection'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'options'   => range(0, 6),
	'reference' => &$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'],
	'eval'      => array('multiple' => true, 'tl_class' => 'clr'),
	'sql'       => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_intAheadWeek'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_intAheadWeek'],
	'exclude'   => true,
	'inputType' => 'text',
	'options'   => range(0, 156),
	'eval'      => array('tl_class' => 'clr', 'rgxp' => 'natural'),
	'sql'       => "int(10) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_addDateStop'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_addDateStop'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_dateStop'],
	'exclude'   => true,
	'default'   => time(),
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "varchar(11) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_dateStop'],
	'exclude'   => true,
	'default'   => time(),
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "varchar(11) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_displayClientPersonalData'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_displayClientPersonalData'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default '1'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_clientPersonalData'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_clientPersonalData'],
	'exclude'          => true,
	'reference'        => &$GLOBALS['TL_LANG']['tl_member'],
	'inputType'        => 'select',
	'options_callback' => array('tl_module_resource_booking', 'getTlMemberFields'),
	'eval'             => array('mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'),
	'sql'              => "varchar(1024) NOT NULL default 'a:2:{i:0;s:9:\"firstname\";i:1;s:8:\"lastname\";}'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_setBookingSubmittedFields'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_setBookingSubmittedFields'],
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default '1'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_bookingSubmittedFields'] = array(
	'label'            => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_bookingSubmittedFields'],
	'exclude'          => true,
	'reference'        => &$GLOBALS['TL_LANG']['tl_resource_booking'],
	'inputType'        => 'select',
	'options_callback' => array('tl_module_resource_booking', 'getTlResourceBookingFields'),
	'eval'             => array('mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'),
	'sql'              => "varchar(1024) NOT NULL default 'a:2:{i:0;s:5:\"title\";i:1;s:11:\"description\";}'"
);

/**
 * Class tl_module_resource_booking
 */
class tl_module_resource_booking extends Backend
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
		$opt = array();
		$objDb = Database::getInstance()->prepare('SELECT * FROM tl_resource_booking_resource_type')->execute();

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
		$arrFieldnames = Database::getInstance()->getFieldNames('tl_member');

		System::loadLanguageFile('tl_member');
		$arrOpt = array();

		foreach ($arrFieldnames as $fieldname)
		{
			if ($fieldname === 'id' || $fieldname === 'password')
			{
				continue;
			}
			$arrOpt[] = $fieldname;
		}

		unset($arrOpt['id'], $arrOpt['password']);

		return $arrOpt;
	}

	/**
	 * Options callback
	 * @return array
	 */
	public function getTlResourceBookingFields(): array
	{
		$arrFieldnames = Database::getInstance()->getFieldNames('tl_resource_booking');
		System::loadLanguageFile('tl_resource_booking');

		return $arrFieldnames;
	}
}
