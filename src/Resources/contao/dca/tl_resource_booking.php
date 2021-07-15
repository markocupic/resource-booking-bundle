<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;

/**
 * Table tl_resource_booking
 */
$GLOBALS['TL_DCA']['tl_resource_booking'] = array(
	// Config
	'config'   => array(
		'dataContainer'    => 'Table',
		'switchToEdit'     => true,
		'ptable'           => 'tl_resource_booking_resource',
		'enableVersioning' => true,
		'notCreatable'     => true,
		'notCopyable'      => true,
		'sql'              => array(
			'keys' => array(
				'id'                           => 'primary',
				'pid,member,startTime,endTime' => 'index',
				'timeSlotId'                   => 'index',
			),
		),
	),
	// List
	'list'     => array(
		'sorting'           => array(
			'mode'        => 2,
			'fields'      => array('startTime'),
			'panelLayout' => 'filter;sort,search,limit'
		),
		'label'             => array(
			'fields'      => array('title'),
			'showColumns' => true
		),
		'global_operations' => array(
			'all' => array(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
		),
		'operations'        => array(
			'edit'   => array(
				'label' => &$GLOBALS['TL_LANG']['tl_resource_booking']['editmeta'],
				'href'  => 'act=edit',
				'icon'  => 'edit.gif',
			),
			'delete' => array(
				'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
			),
			'show'   => array(
				'label' => &$GLOBALS['TL_LANG']['tl_resource_booking']['show'],
				'href'  => 'act=show',
				'icon'  => 'show.gif',
			),
		),
	),
	// Palettes
	'palettes' => array(
		'default' => '{title_legend},title,description,itemsBooked,member,bookingUuid;{time_legend},startTime,endTime',
	),
	// Fields
	'fields'   => array(
		'id'          => array(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'pid'         => array(
			'foreignKey' => 'tl_resource_booking_resource.title',
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'tstamp'      => array(
			'sorting'   => true,
			'flag'      => 6,
			'sql' => "int(10) unsigned NOT NULL default '0'"
		),
		'timeSlotId'  => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'bookingUuid' => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['bookingUuid'],
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'inputType' => 'text',
			'eval'      => array('readonly' => true, 'doNotCopy' => true, 'tl_class' => 'w50'),
			'sql'       => "varchar(64) NOT NULL default ''"
		),
		'member'      => array(
			'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking']['member'],
			'exclude'    => true,
			'search'     => false,
			'sorting'    => false,
			'filter'     => true,
			'inputType'  => 'select',
			'foreignKey' => 'tl_member.CONCAT(firstname," ",lastname)',
			'eval'       => array('mandatory' => true, 'tl_class' => 'w50'),
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'title'       => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['title'],
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'description' => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['description'],
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr'),
			'sql'       => "mediumtext NULL"
		),
        'itemsBooked'      => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking']['itemsBooked'],
            'exclude'    => true,
            'search'     => false,
            'sorting'    => false,
            'filter'     => true,
            'inputType' => 'text',
            'eval'       => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'),
            'sql'        => "int(10) unsigned NOT NULL default '1'",
        ),
		'startTime'   => array(
			'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['startTime'],
			'default'   => time(),
			'sorting'   => true,
			'exclude'   => true,
			'flag'      => 5,
			'inputType' => 'text',
			'eval'      => array('readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'),
			'sql'       => "int(10) NULL"
		),
		'endTime'     => array(
			'label'         => &$GLOBALS['TL_LANG']['tl_resource_booking']['endTime'],
			'sorting'       => true,
			'default'       => time(),
			'exclude'       => true,
			'flag'          => 5,
			'inputType'     => 'text',
			'eval'          => array('readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'),
			'save_callback' => array(
				array('tl_resource_booking', 'setCorrectEndTime')
			),
			'sql'           => "int(10) NULL"
		),
	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 */
class tl_resource_booking extends Backend
{
	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('BackendUser', 'User');
	}
}
