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
use Contao\Image;
use Contao\StringUtil;

$GLOBALS['TL_DCA']['tl_resource_booking_resource_type'] = [
    // Config
    'config'   => [
        'dataContainer'    => 'Table',
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
            'mode'        => 1,
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
                'label'           => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['editheader'],
                'href'            => 'table=tl_resource_booking_resource_type&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => ['tl_resource_booking_resource_type', 'editHeader'],
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
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle'     => [
                'label'                => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['toggle'],
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
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'published'   => [
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['published'],
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
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => 'mediumtext NULL',
        ],
    ],
];

/**
 * Class tl_resource_booking_resource_type.
 */
class tl_resource_booking_resource_type extends Backend
{
    /**
     * Return the edit header button.
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function editHeader($row, $href, $label, $title, $icon, $attributes)
    {
        return '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }
}
