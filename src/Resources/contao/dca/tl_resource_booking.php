<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

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
		'default' => '{booking_legend},title,itemsBooked,member,bookingUuid,description;{module_legend},moduleId;{time_legend},startTime,endTime',
	),
	// Fields
	'fields'   => array(
		'id'          => array(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'pid'         => array(
			'foreignKey' => 'tl_resource_booking_resource.title',
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
			'eval'=> array('mandatory' => true),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
        ),
		'tstamp'      => array(
			'sorting'   => true,
			'flag'      => 6,
			'sql' => "int(10) unsigned NOT NULL default '0'"
		),
		'timeSlotId'  => array(
            'eval'=> array('mandatory' => true),
            'sql' => "int(10) unsigned NOT NULL default '0'",
		),
        'moduleId'         => array(
            'exclude'    => true,
            'inputType' => 'select',
            'options_callback' => array('tl_resource_booking','getRbbModules'),
            'foreignKey' => 'tl_module.name',
            'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
            'eval'=> array('mandatory' => true),
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ),
		'bookingUuid' => array(
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'readonly' => true, 'doNotCopy' => true, 'tl_class' => 'w50'),
			'sql'       => "varchar(64) NOT NULL default ''"
		),
		'member'      => array(
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
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'description' => array(
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr'),
			'sql'       => "mediumtext NULL"
		),
        'itemsBooked'      => array(
            'exclude'    => true,
            'search'     => false,
            'sorting'    => false,
            'filter'     => true,
            'inputType' => 'text',
            'eval'       => array('mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'),
            'sql'        => "int(10) unsigned NOT NULL default '1'",
        ),
		'startTime'   => array(
			'default'   => time(),
			'sorting'   => true,
			'exclude'   => true,
			'flag'      => 5,
			'inputType' => 'text',
			'eval'      => array('readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'),
			'sql'       => "int(10) NULL"
		),
		'endTime'     => array(
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
 * Class tl_resource_booking
 */
class tl_resource_booking extends Backend
{

	public function getRbbModules(): array
    {
        $opt = [];
        $objDb = $this->Database
            ->prepare('SELECT * FROM tl_module WHERE type=?')
            ->execute(ResourceBookingWeekcalendarController::TYPE);

        while($objDb->next())
        {
            $opt[$objDb->id] = $objDb->name;
        }

        return $opt;
    }
}
