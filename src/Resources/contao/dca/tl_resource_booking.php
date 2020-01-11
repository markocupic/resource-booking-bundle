<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

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
            'fields'      => array('startTime DESC'),
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
        'default' => '{title_legend},title,description,member;{time_legend},startTime,endTime',
    ),
    // Fields
    'fields'   => array(
        'id'          => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'tstamp'      => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'timeSlotId'  => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'member'      => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking']['member'],
            'exclude'    => true,
            'search'     => false,
            'sorting'    => false,
            'filter'     => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_member.CONCAT(firstname," ",lastname)',
            'eval'       => array('mandatory' => true, 'tl_class' => 'clr'),
            'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'       => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
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
        'startTime'   => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['startTime'],
            'default'   => time(),
            'exclude'   => true,
            'flag'      => 8,
            'inputType' => 'text',
            'eval'      => array('readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'),
            'sql'       => "int(10) NULL"
        ),
        'endTime'     => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_resource_booking']['endTime'],
            'label'         => &$GLOBALS['TL_LANG']['tl_resource_booking']['startTime'],
            'default'       => time(),
            'exclude'       => true,
            'flag'          => 8,
            'inputType'     => 'text',
            'eval'          => array('readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'),
            'save_callback' => array
            (
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
