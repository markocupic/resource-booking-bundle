<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;

/**
 * Table tl_resource_booking_resource
 */
$GLOBALS['TL_DCA']['tl_resource_booking_resource'] = array(
	// Config
	'config'   => array(
		'dataContainer'    => 'Table',
		'switchToEdit'     => true,
		'ptable'           => 'tl_resource_booking_resource_type',
		'enableVersioning' => true,
		'sql'              => array(
			'keys' => array(
				'id'            => 'primary',
				'published,pid' => 'index',
			),
		),
	),
	// List
	'list'     => array(
		'sorting'           => array(
			'mode'                  => 4,
			'fields'                => array('title ASC'),
			'headerFields'          => array('title'),
			'panelLayout'           => 'filter;sort,search,limit',
			'child_record_callback' => array('tl_resource_booking_resource', 'childRecordCallback')
		),
		'label'             => array
		(
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
			'edit'     => array(
				'href'  => 'act=edit',
				'icon'  => 'edit.gif',
			),
			'bookings' => array(
				'href'  => 'table=tl_resource_booking',
				'icon'  => RbbConfig::RBB_ASSET_PATH . '/icons/calendar.svg',
			),
			'delete'   => array(
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
			),
			'toggle'   => array(
				'attributes'           => 'onclick="Backend.getScrollOffset();"',
				'haste_ajax_operation' => array(
					'field'   => 'published',
					'options' => array(
						array(
							'value' => '',
							'icon'  => 'invisible.svg'
						),
						array(
							'value' => '1',
							'icon'  => 'visible.svg'
						)
					)
				)
			),
			'show'     => array(
				'href'  => 'act=show',
				'icon'  => 'show.gif',
			),
		),
	),
	// Palettes
	'palettes' => array(
		'default' => '{title_legend},title,description,itemsAvailable,timeSlotType',
	),
	// Fields
	'fields'   => array(
		'id'           => array(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'pid'          => array(
			'foreignKey' => 'tl_resource_booking_resource_type.title',
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'tstamp'       => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'title'        => array(
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'flag'      => 1,
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'published'    => array(
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'flag'      => 2,
			'inputType' => 'checkbox',
			'eval'      => array('doNotCopy' => true, 'tl_class' => 'clr'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'description'  => array(
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr'),
			'sql'       => "mediumtext NULL"
		),
        'itemsAvailable'      => array(
            'exclude'    => true,
            'search'     => false,
            'sorting'    => false,
            'filter'     => true,
            'inputType' => 'text',
            'eval'       => array('mandatory' => true, 'rgxp' => 'custom', 'customRgxp' => '/^[1-9]\d*$/', 'tl_class' => 'w50'),
            'sql'        => "int(10) unsigned NOT NULL default '1'",
        ),
		'timeSlotType' => array(
			'inputType'  => 'select',
			'foreignKey' => 'tl_resource_booking_time_slot_type.title',
			'eval'       => array('mandatory' => true, 'tl_class' => 'clr'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy')
		),
	)
);

/**
 * Class tl_resource_booking_resource
 */
class tl_resource_booking_resource extends Backend
{

	/**
	 * @param $row
	 * @return string
	 */
	public function childRecordCallback(array $row): string
	{
		return sprintf('<div class="tl_content_left">' . $row['title'] . '</div>');
	}
}