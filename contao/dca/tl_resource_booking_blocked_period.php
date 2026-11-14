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
 * Blocked periods (e.g. school holidays, weekends): no bookings are possible
 * during a blocked period. A blocked period applies to all resources, to selected
 * resource types or to selected resources. Without "blocked times" the whole
 * period is blocked. With blocked times only the listed weekdays (and times)
 * within the period are blocked. A period without end date is unlimited.
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
        '__selector__' => ['scope'],
        'default'      => '
        {title_legend},title,description;
        {date_legend},dateFrom,dateTo,blockedTimes;
        {scope_legend},scope;
        {publish_legend},published
        ',
    ],
    'subpalettes' => [
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
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
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
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'notnull' => true, 'default' => 0],
        ],
        'blockedTimes'  => [
            'exclude'   => true,
            'inputType' => 'rowWizard',
            'fields'    => [
                'weekday'  => [
                    'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_weekday'],
                    'inputType' => 'select',
                    'options'   => ['daily', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                    'reference' => &$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions'],
                ],
                'timeFrom' => [
                    'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_timeFrom'],
                    'inputType' => 'text',
                    'eval'      => ['maxlength' => 5, 'placeholder' => 'hh:mm'],
                ],
                'timeTo'   => [
                    'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_timeTo'],
                    'inputType' => 'text',
                    'eval'      => ['maxlength' => 5, 'placeholder' => 'hh:mm'],
                ],
            ],
            'eval'      => ['tl_class' => 'clr', 'actions' => ['copy', 'delete']],
            'sql'       => 'blob NULL',
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
