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

$GLOBALS['TL_DCA']['tl_resource_booking_resource_type'] = [
    // Config
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'switchToEdit'     => true,
        'ctable'           => ['tl_resource_booking_resource'],
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    // List
    'list'     => [
        'sorting'           => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['title ASC'],
            'flag'        => 1,
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
            'edit'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['editmeta'],
                'href'  => 'table=tl_resource_booking_resource',
                'icon'  => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['editheader'],
                'href'  => 'table=tl_resource_booking_resource_type&amp;act=edit',
                'icon'  => 'header.svg',
            ],
            'cut'        => [
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['cut'],
                'href'  => 'act=paste&amp;mode=cut',
                'icon'  => 'cut.gif',
            ],
            'delete'     => [
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle'     => [
                'href'         => 'act=toggle&amp;field=published',
                'icon'         => 'visible.svg',
                //'button_callback' => ['tl_article', 'toggleIcon'],
                'showInHeader' => true,
            ],
            'show'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif',
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
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp'      => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'       => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
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
