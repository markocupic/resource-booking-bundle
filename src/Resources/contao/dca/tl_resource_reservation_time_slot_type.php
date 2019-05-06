<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */


$GLOBALS['TL_DCA']['tl_resource_reservation_time_slot_type'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'ctable'                      => array('tl_resource_reservation_time_slot'),
		'switchToEdit'                => true,
		'enableVersioning'            => true,
		'onload_callback' => array
		(

		),
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'published' => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 4,
			'fields'                  => array('published DESC', 'title'),
			//'paste_button_callback'   => array('tl_resource_reservation_time_slot_type', 'pasteArticle'),
			'panelLayout'             => 'filter;search',
            'headerFields'            => array('title'),

        ),
		'label' => array
		(
			'fields'                  => array('title'),
			'format'                  => '%s',
			//'label_callback'          => array('tl_resource_reservation_time_slot_type', 'addIcon')
		),
		'global_operations' => array
		(

			'all' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['edit'],
				'href'                => 'table=tl_content',
				'icon'                => 'edit.svg',
				//'button_callback'     => array('tl_resource_reservation_time_slot_type', 'editArticle')
			),
			'editheader' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['editheader'],
				'href'                => 'act=edit',
				'icon'                => 'header.svg',
				//'button_callback'     => array('tl_resource_reservation_time_slot_type', 'editHeader')
			),
			'copy' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['copy'],
				'href'                => 'act=paste&amp;mode=copy',
				'icon'                => 'copy.svg',
				'attributes'          => 'onclick="Backend.getScrollOffset()"',
				//'button_callback'     => array('tl_resource_reservation_time_slot_type', 'copyArticle')
			),

			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
				//'button_callback'     => array('tl_resource_reservation_time_slot_type', 'deleteArticle')
			),
			'toggle' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['toggle'],
				'icon'                => 'visible.svg',
				'attributes'          => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
				//'button_callback'     => array('tl_resource_reservation_time_slot_type', 'toggleIcon'),
				'showInHeader'        => true
			),
			'show' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['show'],
				'href'                => 'act=show',
				'icon'                => 'show.svg'
			)
		)
	),

	// Select
	'select' => array
	(
		'buttons_callback' => array
		(
			//array('tl_resource_reservation_time_slot_type', 'addAliasButton')
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{title_legend},title,description'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'label'                   => array('ID'),
			'search'                  => true,
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'title' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['title'],
			'exclude'                 => true,
			'inputType'               => 'text',
			'search'                  => true,
			'eval'                    => array('mandatory'=>true, 'decodeEntities'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
        'published'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['published'],
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
            'label'                   => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot_type']['description'],
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'textarea',
            'eval'                    => array(),
            'sql'                     => "mediumtext NULL"
		),

	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_reservation_time_slot_type extends Contao\Backend
{

	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import('Contao\BackendUser', 'User');
	}
}
