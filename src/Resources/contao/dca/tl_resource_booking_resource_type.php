<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

$GLOBALS['TL_DCA']['tl_resource_booking_resource_type'] = array(

    // Config
    'config'   => array(
        'dataContainer'    => 'Table',
        'switchToEdit'     => true,
        'ctable'           => array('tl_resource_booking_resource'),
        'enableVersioning' => true,
        'sql'              => array(
            'keys' => array(
                'id' => 'primary',
            ),
        ),
    ),
    // List
    'list'     => array(
        'sorting'           => array(
            'mode'        => 1,
            'fields'      => array('title ASC'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array
        (
            'fields'      => array('title'),
            'showColumns' => true
        ),
        'global_operations' => array(
            'all' => array(
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ),
        ),
        'operations'        => array(

            'edit'       => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['editmeta'],
                'href'  => 'table=tl_resource_booking_resource',
                'icon'  => 'edit.gif',
            ),
            'editheader' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot_type']['editheader'],
                'href'            => 'table=tl_resource_booking_resource_type&amp;act=edit',
                'icon'            => 'header.svg',
                'button_callback' => array('tl_resource_booking_resource_type', 'editHeader')
            ),
            'cut'        => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['cut'],
                'href'  => 'act=paste&amp;mode=cut',
                'icon'  => 'cut.gif',
            ),
            'delete'     => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
            ),
            'show'       => array(
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif',
            ),
        ),
    ),
    // Palettes
    'palettes' => array(
        'default' => '{title_legend},title,description',
    ),
    // Fields
    'fields'   => array(
        'id'          => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp'      => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'title'       => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'description' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_resource_type']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr'),
            'sql'       => "mediumtext NULL"
        )
    )

);

/**
 * Class tl_resource_booking_resource_type
 */
class tl_resource_booking_resource_type extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * Return the edit header button
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
        return '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . Contao\StringUtil::specialchars($title) . '"' . $attributes . '>' . Contao\Image::getHtml($icon, $label) . '</a> ';
    }

}
