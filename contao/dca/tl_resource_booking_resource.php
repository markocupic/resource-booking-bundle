<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Contao\DC_Table;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_resource_booking_resource'] = [
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'switchToEdit'     => true,
        'ptable'           => 'tl_resource_booking_resource_type',
        'ctable'           => ['tl_resource_booking'],
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'            => 'primary',
                'published,pid' => 'index',
            ],
        ],
    ],
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
            'edit',
            'bookings' => [
                'href' => 'table=tl_resource_booking',
                'icon' => RbbConfig::RBB_ASSET_PATH . '/icons/calendar.svg',
            ],
            'delete',
            'toggle',
            'show',
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,description,itemsAvailable,timeSlotType',
    ],
    'fields'   => [
        'id'             => [
            'sql' => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'autoincrement' => true],
        ],
        'pid'            => [
            'foreignKey' => 'tl_resource_booking_resource_type.title',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'tstamp'         => [
            'sql' => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'title'          => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'flag'      => 1,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
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
            'sql'       => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 1],
        ],
        'timeSlotType'   => [
            'inputType'  => 'select',
            'foreignKey' => 'tl_resource_booking_time_slot_type.title',
            'eval'       => ['mandatory' => true, 'tl_class' => 'clr'],
            'sql'        => ['type' => 'integer', 'length' => 11, 'unsigned' => true, 'notnull' => true, 'default' => 0],
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
    ],
];
