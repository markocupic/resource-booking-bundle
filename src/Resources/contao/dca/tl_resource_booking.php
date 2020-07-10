<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Table tl_resource_booking
 */
$GLOBALS['TL_DCA']['tl_resource_booking'] = [

    // Config
    'config'   => [
        'dataContainer'    => 'Table',
        'switchToEdit'     => true,
        'ptable'           => 'tl_resource_booking_resource',
        'enableVersioning' => true,
        'notCreatable'     => true,
        'notCopyable'      => true,
        'sql'              => [
            'keys' => [
                'id'                           => 'primary',
                'pid,member,startTime,endTime' => 'index',
                'timeSlotId'                   => 'index',
            ],
        ],
    ],
    // List
    'list'     => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['startTime'],
            'panelLayout' => 'filter;sort,search,limit'
        ],
        'label'             => [
            'fields'      => ['title'],
            'showColumns' => true
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [

            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking']['editmeta'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ],
            'show'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif',
            ],
        ],
    ],
    // Palettes
    'palettes' => [
        'default' => '{title_legend},title,description,member,bookingUuid;{time_legend},startTime,endTime',
    ],
    // Fields
    'fields'   => [
        'id'          => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'pid'         => [
            'foreignKey' => 'tl_resource_booking_resource.title',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'      => [
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ],
        'timeSlotId'  => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'bookingUuid' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['bookingUuid'],
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'doNotCopy' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''"
        ],
        'member'      => [
            'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking']['member'],
            'exclude'    => true,
            'search'     => false,
            'sorting'    => false,
            'filter'     => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_member.CONCAT(firstname," ",lastname)',
            'eval'       => ['mandatory' => true, 'tl_class' => 'w50'],
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'       => [
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['title'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'description' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => "mediumtext NULL"
        ],
        'startTime'   => [
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking']['startTime'],
            'default'   => time(),
            'sorting'   => true,
            'exclude'   => true,
            'flag'      => 5,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'],
            'sql'       => "int(10) NULL"
        ],
        'endTime'     => [
            'label'         => &$GLOBALS['TL_LANG']['tl_resource_booking']['endTime'],
            'sorting'       => true,
            'default'       => time(),
            'exclude'       => true,
            'flag'          => 5,
            'inputType'     => 'text',
            'eval'          => ['readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'],
            'save_callback' => [
                ['tl_resource_booking', 'setCorrectEndTime']
            ],
            'sql'           => "int(10) NULL"
        ],
    ]

];

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
