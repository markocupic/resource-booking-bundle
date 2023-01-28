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

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Contao\DC_Table;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_resource_booking_resource'] = [
    // Config
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'switchToEdit'     => true,
        'ptable'           => 'tl_resource_booking_resource_type',
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'            => 'primary',
                'published,pid' => 'index',
            ],
        ],
    ],
    // List
    'list'     => [
        'sorting'           => [
            'mode'         => DataContainer::MODE_PARENT,
            'fields'       => ['title ASC'],
            'headerFields' => ['title'],
            'panelLayout'  => 'filter;sort,search,limit',
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
            'edit'     => [
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'bookings' => [
                'href' => 'table=tl_resource_booking',
                'icon' => RbbConfig::RBB_ASSET_PATH.'/icons/calendar.svg',
            ],
            'delete'   => [
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle'   => [
                'href'         => 'act=toggle&amp;field=published',
                'icon'         => 'visible.svg',
                'showInHeader' => true,
            ],
            'show'     => [
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],
    // Palettes
    'palettes' => [
        'default' => '{title_legend},title,description,itemsAvailable,timeSlotType',
    ],
    // Fields
    'fields'   => [
        'id'             => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid'            => [
            'foreignKey' => 'tl_resource_booking_resource_type.title',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'         => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'          => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'flag'      => 1,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'published'      => [
            'toggle'    => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'description'    => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => 'mediumtext NULL',
        ],
        'itemsAvailable' => [
            'exclude'   => true,
            'search'    => false,
            'sorting'   => false,
            'filter'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'rgxp' => 'custom', 'customRgxp' => '/^[1-9]\d*$/', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default '1'",
        ],
        'timeSlotType'   => [
            'inputType'  => 'select',
            'foreignKey' => 'tl_resource_booking_time_slot_type.title',
            'eval'       => ['mandatory' => true, 'tl_class' => 'clr'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
    ],
];
