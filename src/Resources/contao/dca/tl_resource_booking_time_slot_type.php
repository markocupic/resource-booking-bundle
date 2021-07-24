<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;

/**
 * Table tl_resource_booking_time_slot_type
 */
$GLOBALS['TL_DCA']['tl_resource_booking_time_slot_type'] = array
(
	// Config
	'config'   => array
	(
		'dataContainer'     => 'Table',
		'ctable'            => array('tl_resource_booking_time_slot'),
		'switchToEdit'      => true,
		'enableVersioning'  => true,
		'sql'               => array
		(
			'keys' => array
			(
				'id' => 'primary',
			)
		),
		'ondelete_callback' => array(array('tl_resource_booking_time_slot_type', 'removeChildRecords'))
	),

	// List
	'list'     => array
	(
		'sorting'           => array
		(
			'mode'        => 1,
			'fields'      => array('title'),
			'flag'        => 1,
			'panelLayout' => 'filter;search,limit'
		),
		'label'             => array
		(
			'fields' => array('title'),
			'format' => '%s'
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)
		),
		'operations'        => array
		(
			'edit'       => array
			(
				'href'  => 'table=tl_resource_booking_time_slot',
				'icon'  => 'edit.svg'
			),
			'editheader' => array
			(
				'href'            => 'table=tl_resource_booking_time_slot_type&amp;act=edit',
				'icon'            => 'header.svg',
				'button_callback' => array('tl_resource_booking_time_slot_type', 'editHeader')
			),
			'copy'       => array
			(
				'href'  => 'act=copy',
				'icon'  => 'copy.svg'
			),
			'delete'     => array
			(
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
			),
			'toggle'     => array(
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
			'show'       => array
			(
				'href'  => 'act=show',
				'icon'  => 'show.svg'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'default' => '{title_legend},title,description'
	),

	// Fields
	'fields'   => array
	(
		'id'          => array
		(
			'label'  => array('ID'),
			'search' => true,
			'sql'    => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp'      => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'"
		),
		'title'       => array
		(
			'exclude'   => true,
			'inputType' => 'text',
			'search'    => true,
			'eval'      => array('mandatory' => true, 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
			'sql'       => "varchar(255) NOT NULL default ''"
		),
		'published'   => array(
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'flag'      => 2,
			'inputType' => 'checkbox',
			'eval'      => array('doNotCopy' => true, 'tl_class' => 'clr'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'description' => array
		(
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr'),
			'sql'       => "mediumtext NULL"
		)
	)
);


class tl_resource_booking_time_slot_type extends Backend
{


	/**
	 * Return the edit header button
	 *
	 * @param  array  $row
	 * @param  string $href
	 * @param  string $label
	 * @param  string $title
	 * @param  string $icon
	 * @param  string $attributes
	 * @return string
	 */
	public function editHeader(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
	{
		return '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
	}

	/**
	 * ondelete_callback
	 * @param DataContainer $dc
	 */
	public function removeChildRecords(DataContainer $dc)
	{
		if (!$dc->id)
		{
			return;
		}
		// Delete child bookings
		$this->Database->prepare('DELETE FROM tl_resource_booking WHERE tl_resource_booking.timeSlotId IN (SELECT id FROM tl_resource_booking_time_slot WHERE tl_resource_booking_time_slot.pid=?)')->execute($dc->id);

		// Delete time slot children
		$this->Database->prepare('DELETE FROM tl_resource_booking_time_slot WHERE pid=?')->execute($dc->id);
	}
}
