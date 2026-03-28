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

use Contao\DataContainer;
use Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\RegExpListener;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_resource_booking_time_slot'] = [
    'config'   => [
        'dataContainer'    => DC_Table::class,
        'ptable'           => 'tl_resource_booking_time_slot_type',
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list'     => [
        'sorting'           => [
            'mode'         => DataContainer::MODE_PARENT,
            'fields'       => ['sorting'],
            'panelLayout'  => 'filter;search,limit',
            'headerFields' => ['title'],
        ],
        'global_operations' => [
            'all' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => 'all',
    ],
    'palettes' => [
        'default' => '
        {title_legend},title,description;
        {time_legend},startTime,endTime;
        {expert_legend:hide},cssID
        ',
    ],
    'fields'   => [
        'id'          => [
            'sql' => ['type' => 'integer', 'length' => 11, 'notnull' => true, 'unsigned' => true, 'autoincrement' => true],
        ],
        'pid'         => [
            'foreignKey' => 'tl_resource_booking_time_slot_type.title',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => ['type' => 'integer', 'length' => 11, 'notnull' => true, 'unsigned' => true, 'default' => 0],
        ],
        'tstamp'      => [
            'sql' => ['type' => 'integer', 'length' => 10, 'notnull' => true, 'unsigned' => true, 'default' => 0],
        ],
        'sorting'     => [
            'sql' => ['type' => 'integer', 'length' => 10, 'notnull' => true, 'unsigned' => true, 'default' => 0],
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
        'startTime'   => [
            'default'   => time(),
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_MONTH_DESC,
            'inputType' => 'text',
            'eval'      => ['rgxp' => RegExpListener::REGEX_RESOURCE_BOOKING_TIME, 'mandatory' => true, 'tl_class' => 'w50'],
            'sql'       => ['type' => 'integer', 'length' => 10, 'notnull' => true, 'unsigned' => true, 'default' => 0],
        ],
        'endTime'     => [
            'default'   => time(),
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => RegExpListener::REGEX_RESOURCE_BOOKING_TIME, 'mandatory' => true, 'tl_class' => 'w50'],
            'sql'       => ['type' => 'integer', 'length' => 10, 'notnull' => true, 'unsigned' => true, 'default' => 0],
        ],
        'cssID'       => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['multiple' => true, 'size' => 2, 'tl_class' => 'w50 clr'],
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
    ],
];
