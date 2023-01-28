<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\DC_Table;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_resource_booking'] = [
    // Config
    'config'   => [
        'dataContainer'    => DC_Table::class,
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
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['startTime'],
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields'      => ['title'],
            'showColumns' => true,
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
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
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
        'default' => '{booking_legend},title,itemsBooked,member,bookingUuid,description;{module_legend},moduleId;{time_legend},startTime,endTime',
    ],
    // Fields
    'fields'   => [
        'id'          => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid'         => [
            'foreignKey' => 'tl_resource_booking_resource.title',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'eval'       => ['mandatory' => true],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'      => [
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'sql'     => "int(10) unsigned NOT NULL default '0'",
        ],
        'timeSlotId'  => [
            'eval' => ['mandatory' => true],
            'sql'  => "int(10) unsigned NOT NULL default '0'",
        ],
        'moduleId'    => [
            'exclude'    => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_module.name',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'eval'       => ['mandatory' => true],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'bookingUuid' => [
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'readonly' => true, 'doNotCopy' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'member'      => [
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
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'description' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => 'mediumtext NULL',
        ],
        'itemsBooked' => [
            'exclude'   => true,
            'search'    => false,
            'sorting'   => false,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default '1'",
        ],
        'startTime'   => [
            'default'   => time(),
            'sorting'   => true,
            'exclude'   => true,
            'flag'      => DataContainer::SORT_DAY_ASC,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'],
            'sql'       => 'int(10) NULL',
        ],
        'endTime'     => [
            'sorting'       => true,
            'default'       => time(),
            'exclude'       => true,
            'flag'          => DataContainer::SORT_DAY_ASC,
            'inputType'     => 'text',
            'eval'          => ['readonly' => true, 'rgxp' => 'datim', 'mandatory' => true, 'doNotCopy' => true, 'datepicker' => false, 'tl_class' => 'w50 wizard'],
            'save_callback' => [
                ['tl_resource_booking', 'setCorrectEndTime'],
            ],
            'sql'           => 'int(10) NULL',
        ],
    ],
];
