<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;

/*
 * Table tl_resource_booking_time_slot_type
 */
$GLOBALS['TL_DCA']['tl_resource_booking_time_slot_type'] = [
    // Config
    'config'   => [
        'dataContainer'     => 'Table',
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
            'mode'        => 1,
            'fields'      => ['title'],
            'flag'        => 1,
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
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle'     => [
                'attributes'           => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field'   => 'published',
                    'options' => [
                        [
                            'value' => '',
                            'icon'  => 'invisible.svg',
                        ],
                        [
                            'value' => '1',
                            'icon'  => 'visible.svg',
                        ],
                    ],
                ],
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
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'flag'      => 2,
            'inputType' => 'checkbox',
            'eval'      => ['doNotCopy' => true, 'tl_class' => 'clr'],
            'sql'       => "char(1) NOT NULL default ''",
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

class tl_resource_booking_time_slot_type extends Backend
{
    /**
     * Return the edit header button.
     */
    public function editHeader(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        return '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * ondelete_callback.
     */
    public function removeChildRecords(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }
        // Delete child bookings
        $this->Database->prepare('DELETE FROM tl_resource_booking WHERE tl_resource_booking.timeSlotId IN (SELECT id FROM tl_resource_booking_time_slot WHERE tl_resource_booking_time_slot.pid=?)')->execute($dc->id);

        // Delete time slot children
        $this->Database->prepare('DELETE FROM tl_resource_booking_time_slot WHERE pid=?')->execute($dc->id);
    }
}
