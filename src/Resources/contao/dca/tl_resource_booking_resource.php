<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */

/**
 * Table tl_calendar_events
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
                'id'        => 'primary',
                'published,pid' => 'index',
            ),
        ),
    ),
    // List
    'list'     => array(
        'sorting'           => array(
            'mode'        => 0,
            'fields'      => array('title ASC'),
            'panelLayout' => 'filter;sort,search,limit'
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

            'edit'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['editmeta'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['copy'],
                'href'  => 'act=paste&amp;mode=copy',
                'icon'  => 'copy.gif',
            ),
            'cut'    => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['cut'],
                'href'  => 'act=paste&amp;mode=cut',
                'icon'  => 'cut.gif',
            ),
            'delete' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ),
            'toggle' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['toggle'],
                'icon'       => 'visible.gif',
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                //'button_callback' => array('tl_resource_booking_resource', 'toggleIcon'),
            ),
            'show'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif',
            ),
        ),
    ),
    // Palettes
    'palettes' => array(
        'default' => '{published_legend},published;{title_legend},pid,title,description,timeSlotType',
    ),
    // Fields
    'fields'   => array(
        'id'           => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'          => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['pid'],
            'inputType'  => 'select',
            'foreignKey' => 'tl_resource_booking_resource_type.title',
            'eval'       => array('mandatory' => true),
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'lazy')
        ),
        'tstamp'       => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'        => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'published'    => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['published'],
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
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr'),
            'sql'       => "mediumtext NULL"
        ),
        'timeSlotType' => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_resource']['timeSlotType'],
            'inputType'  => 'select',
            'foreignKey' => 'tl_resource_booking_time_slot_type.title',
            'eval'       => array('mandatory' => true, 'tl_class' => 'clr'),
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'lazy')
        ),
    )

);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_booking_resource extends Backend
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
