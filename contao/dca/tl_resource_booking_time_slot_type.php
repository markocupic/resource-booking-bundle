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

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_resource_booking_time_slot_type'] = [
    // Config
    'config'   => [
        'dataContainer'     => DC_Table::class,
        'ctable'            => ['tl_resource_booking_time_slot'],
        'switchToEdit'      => true,
        'enableVersioning'  => true,
        'sql'               => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
        'ondelete_callback' => [['tl_resource_booking_time_slot_type', 'removeChildRecords']],
    ],
    // List
    'list'     => [
        'sorting'           => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['title'],
            'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label'             => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'       => [
                'href' => 'table=tl_resource_booking_time_slot',
                'icon' => 'edit.svg',
            ],
            'editheader' => [
                'href'            => 'table=tl_resource_booking_time_slot_type&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => ['tl_resource_booking_time_slot_type', 'editHeader'],
            ],
            'copy'       => [
                'href' => 'act=copy',
                'icon' => 'copy.svg',
            ],
            'delete'     => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle'     => [
                'href'         => 'act=toggle&amp;field=published',
                'icon'         => 'visible.svg',
                'showInHeader' => true,
            ],
            'show'       => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        'default' => '{title_legend},title,description',
    ],

    // Fields
    'fields'   => [
        'id'          => [
            'label'  => ['ID'],
            'search' => true,
            'sql'    => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp'      => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'       => [
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'published'   => [
            'toggle'    => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'description' => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => 'mediumtext NULL',
        ],
    ],
];


