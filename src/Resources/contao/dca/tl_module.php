<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Contao\System;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;

/**
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['resourceBookingWeekcalendar'] = '{title_legend},name,headline,type;{config_legend},resourceBooking_appConfig,resourceBooking_resourceTypes,resourceBooking_hideDays,resourceBooking_addDateStop,resourceBooking_displayClientPersonalData,resourceBooking_setBookingSubmittedFields;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';
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
	'exclude'          => true,
	'inputType'        => 'checkbox',
	'options_callback' => array('tl_module_resource_booking', 'getResourceTypes'),
	'eval'             => array('multiple' => true, 'tl_class' => 'clr'),
	'sql'              => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDays'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_hideDaysSelection'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'options'   => RbbConfig::RBB_WEEKDAYS,
	'reference' => &$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'],
	'eval'      => array('multiple' => true, 'tl_class' => 'clr'),
	'sql'       => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_addDateStop'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = array(
	'exclude'   => true,
	'default'   => time(),
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "varchar(11) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_dateStop'] = array(
	'exclude'   => true,
	'default'   => time(),
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "varchar(11) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_displayClientPersonalData'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default '1'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_clientPersonalData'] = array(
	'exclude'          => true,
	'reference'        => &$GLOBALS['TL_LANG']['tl_member'],
	'inputType'        => 'select',
	'options_callback' => array('tl_module_resource_booking', 'getTlMemberFields'),
	'eval'             => array('mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'),
	'sql'              => "varchar(1024) NOT NULL default 'a:2:{i:0;s:9:\"firstname\";i:1;s:8:\"lastname\";}'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_setBookingSubmittedFields'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default '1'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_bookingSubmittedFields'] = array(
	'exclude'          => true,
	'reference'        => &$GLOBALS['TL_LANG']['tl_resource_booking'],
	'inputType'        => 'select',
	'options_callback' => array('tl_module_resource_booking', 'getTlResourceBookingFields'),
	'eval'             => array('mandatory' => true, 'chosen' => true, 'multiple' => true, 'tl_class' => 'clr'),
	'sql'              => "varchar(1024) NOT NULL default 'a:2:{i:0;s:5:\"title\";i:1;s:11:\"description\";}'"
);

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_appConfig'] = array(
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => array('tl_module_resource_booking', 'getAppConfigurations'),
    'eval'             => array('mandatory' => true, 'multiple' => false, 'tl_class' => 'clr'),
    'sql'              => "varchar(64) NOT NULL default 'default'"
);

/**
 * Class tl_module_resource_booking
 */
class tl_module_resource_booking extends Backend
{


	/**
	 * @return array
	 */
	public function getResourceTypes(): array
	{
		$opt = array();
		$objDb = $this->Database
            ->prepare('SELECT * FROM tl_resource_booking_resource_type')
            ->execute()
        ;

		while ($objDb->next())
		{
			$opt[$objDb->id] = $objDb->title;
		}

		return $opt;
	}

    /**
     * @return array
     */
    public function getAppConfigurations(): array
    {
        $appConfig = System::getContainer()->getParameter('markocupic_resource_booking.apps');
        $opt = array_keys($appConfig);

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
		$arrFieldnames = $this->Database->getFieldNames('tl_member');

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
		$arrFieldnames = $this->Database->getFieldNames('tl_resource_booking');
		System::loadLanguageFile('tl_resource_booking');

		return $arrFieldnames;
	}
}
