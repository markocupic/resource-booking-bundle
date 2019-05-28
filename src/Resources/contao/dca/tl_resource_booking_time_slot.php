<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

$GLOBALS['TL_DCA']['tl_resource_booking_time_slot'] = array
(

    // Config
    'config'   => array
    (
        'dataContainer'     => 'Table',
        'ptable'            => 'tl_resource_booking_time_slot_type',
        'enableVersioning'  => true,
        'sql'               => array
        (
            'keys' => array
            (
                'id'  => 'primary',
                'pid' => 'index'
            )
        ),
        'ondelete_callback' => array(array('tl_resource_booking_time_slot', 'removeChildRecords'))
    ),

    // List
    'list'     => array
    (
        'sorting'           => array
        (
            'mode'                  => 4,
            'fields'                => array('sorting'),
            'panelLayout'           => 'filter;search,limit',
            'headerFields'          => array('title'),
            'child_record_callback' => array('tl_resource_booking_time_slot', 'childRecordCallback')
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),
            'copy'   => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['copy'],
                'href'       => 'act=paste&amp;mode=copy',
                'icon'       => 'copy.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ),
            'cut'    => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['cut'],
                'href'       => 'act=paste&amp;mode=cut',
                'icon'       => 'cut.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'toggle' => array
            (
                'label'                => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['toggle'],
                'attributes'           => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'haste_ajax_operation' => [
                    'field'   => 'published',
                    'options' => [
                        [
                            'value' => '',
                            'icon'  => 'invisible.svg'
                        ],
                        [
                            'value' => '1',
                            'icon'  => 'visible.svg'
                        ]
                    ]
                ]
            ),
            'show'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg'
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{title_legend},title,description;{time_legend},startTime,endTime',
    ),

    // Fields
    'fields'   => array
    (
        'id'          => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'tstamp'      => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'sorting'     => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'title'       => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'published'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['published'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'flag'      => 2,
            'inputType' => 'checkbox',
            'eval'      => array('doNotCopy' => true, 'tl_class' => 'clr'),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'description' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_booking_time_slot']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr'),
            'sql'       => "mediumtext NULL"
        ),
        'startTime'   => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['startTime'],
            'default'       => time(),
            'exclude'       => true,
            'filter'        => true,
            'sorting'       => true,
            'flag'          => 8,
            'inputType'     => 'text',
            'eval'          => array('rgxp' => 'resourceBookingTime', 'mandatory' => true, 'tl_class' => 'w50'),
            'load_callback' => array
            (
                array('tl_resource_booking_time_slot', 'loadTime')
            ),
            'save_callback' => array
            (
                array('tl_resource_booking_time_slot', 'setCorrectTime')
            ),
            'sql'           => "int(10) NULL"
        ),
        'endTime'     => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['endTime'],
            'default'       => time(),
            'exclude'       => true,
            'inputType'     => 'text',
            'eval'          => array('rgxp' => 'resourceBookingTime', 'mandatory' => true, 'tl_class' => 'w50'),
            'load_callback' => array
            (
                array('tl_resource_booking_time_slot', 'loadTime')
            ),
            'save_callback' => array
            (
                array('tl_resource_booking_time_slot', 'setCorrectTime'),
                array('tl_resource_booking_time_slot', 'setCorrectEndTime')
            ),
            'sql'           => "int(10) NULL"
        ),
    )
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_booking_time_slot extends Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');
    }

    /**
     * @param $row
     * @return string
     */
    public function childRecordCallback($row)
    {
        return sprintf('<div class="tl_content_left"><span style="color:#999;padding-left:3px">' . $row['title'] . '</span> %s-%s</div>', Markocupic\ResourceBookingBundle\UtcDate::parse('H:i', $row['startTime']), Markocupic\ResourceBookingBundle\UtcDate::parse('H:i', $row['endTime']));
    }

    /**
     * @param $timestamp
     * @return string
     */
    public function loadTime($timestamp)
    {
        $strValue = '';
        if ($timestamp != '')
        {
            $strValue = Markocupic\ResourceBookingBundle\UtcDate::parse('H:i', $timestamp);
        }

        return $strValue;
    }

    /**
     * @param $varValue
     * @param \Contao\DataContainer $dc
     * @return false|int
     */
    public function setCorrectTime($varValue, Contao\DataContainer $dc)
    {
        if (strlen($varValue) === 5)
        {
            $varValue = Markocupic\ResourceBookingBundle\UtcDate::strtotime('1970-01-01 ' . $varValue);
        }
        else
        {
            $varValue = 0;
        }

        return $varValue;
    }

    /**
     * Adjust endTime if it is smaller then the startTime
     * @param $varValue
     * @param \Contao\DataContainer $dc
     * @return false|int
     */
    public function setCorrectEndTime($varValue, Contao\DataContainer $dc)
    {
        // Adjust endTime if it is smaller then the startTime
        if (Contao\Input::post('startTime') != '')
        {
            $strStartTime = Contao\Input::post('startTime');
        }
        else
        {
            $strStartTime = $dc->activeRecord->startTime;
        }

        if ($strStartTime != '')
        {
            $startTime = \Markocupic\ResourceBookingBundle\UtcDate::strtotime('01-01-1970 ' . $strStartTime);
            if ($startTime !== false)
            {
                if ($varValue <= $startTime)
                {
                    $varValue = $startTime + 60;
                }
            }
            else
            {
                $varValue = 0;
            }
        }
        else
        {
            $varValue = 0;
        }

        return $varValue;
    }

    /**
     * ondelete_callback
     * @param \Contao\DataContainer $dc
     */
    public function removeChildRecords(Contao\DataContainer $dc)
    {
        if (!$dc->id)
        {
            return;
        }
        // Delete child bookings
        $this->Database->prepare('DELETE FROM tl_resource_booking WHERE timeSlotId=?')->execute($dc->id);
    }

}
