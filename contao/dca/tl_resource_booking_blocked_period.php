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
use Contao\DC_Table;

/*
 * Blocked periods (e.g. school holidays): no bookings are possible during
 * a blocked period. A blocked period applies to all resources, to selected
 * resource types or to selected resources and can be limited to a time range
 * (the time range applies to every day of the period).
 */
$GLOBALS['TL_DCA']['tl_resource_booking_blocked_period'] = [
    'config'      => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id'               => 'primary',
                'dateFrom,dateTo'  => 'index',
            ],
        ],
    ],
    'list'        => [
        'sorting'           => [
            'mode'        => DataContainer::MODE_SORTED,
            'fields'      => ['dateFrom DESC'],
            'flag'        => DataContainer::SORT_YEAR_DESC,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields'      => ['title', 'dateFrom', 'dateTo'],
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
    'palettes'    => [
        '__selector__' => ['addTimeRange', 'scope'],
        'default'      => '
        {title_legend},title,description;
        {date_legend},dateFrom,dateTo,addTimeRange;
        {scope_legend},scope;
        {publish_legend},published
        ',
    ],
    'subpalettes' => [
        'addTimeRange'        => 'timeFrom,timeTo',
        'scope_resourceTypes' => 'resourceTypes',
        'scope_resources'     => 'resources',
    ],
    'fields'      => [
        'id'            => [
            'sql' => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'autoincrement' => true],
        ],
        'tstamp'        => [
            'sql' => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'title'         => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => ['type' => 'string', 'length' => 255, 'notnull' => true, 'default' => ''],
        ],
        'description'   => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => 'mediumtext NULL',
        ],
        'dateFrom'      => [
            'default'   => strtotime('today'),
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_YEAR_DESC,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'dateTo'        => [
            'default'   => strtotime('today'),
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'date', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'addTimeRange'  => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
        'timeFrom'      => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'time', 'datepicker' => true, 'mandatory' => true, 'tl_class' => 'w50 wizard'],
            // Contao stores the time as timestamp of 1970-01-01 in the local time zone (may be negative)
            'sql'       => ['type' => 'integer', 'notnull' => false, 'default' => null],
        ],
        'timeTo'        => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'time', 'datepicker' => true, 'mandatory' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => ['type' => 'integer', 'notnull' => false, 'default' => null],
        ],
        'scope'         => [
            'default'   => 'all',
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['all', 'resourceTypes', 'resources'],
            'reference' => &$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions'],
            'eval'      => ['submitOnChange' => true, 'tl_class' => 'w50'],
            'sql'       => ['type' => 'string', 'length' => 16, 'notnull' => true, 'default' => 'all'],
        ],
        'resourceTypes' => [
            'exclude'    => true,
            'inputType'  => 'checkbox',
            'foreignKey' => 'tl_resource_booking_resource_type.title',
            'eval'       => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'sql'        => 'blob NULL',
        ],
        'resources'     => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'sql'       => 'blob NULL',
        ],
        'published'     => [
            'default'   => true,
            'toggle'    => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true],
            'sql'       => ['type' => 'boolean', 'default' => false],
        ],
    ],
];
