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

use Contao\DC_Table;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_resource_booking_resource_type'] = [
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
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'all',
        ],
    ],
    'palettes' => [
        'default' => '{title_legend},title,description',
    ],
    'fields'   => [
        'id'          => [
            'sql' => ['type' => 'integer', 'length' => 11, 'unsigned' => true, 'notnull' => true, 'autoincrement' => true],
        ],
        'tstamp'      => [
            'sql' => ['type' => 'integer', 'length' => 11, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'title'       => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
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
