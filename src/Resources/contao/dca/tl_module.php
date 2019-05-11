<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */


/**
 * Add palettes to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['resourceBookingWeekCalendar'] = '{title_legend},name,headline,type;{config_legend},resourceBooking_resourceTypes;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['fields']['resourceBooking_resourceTypes'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['resourceBooking_resourceTypes'],
    'exclude'          => true,
    'inputType'        => 'checkbox',
    'options_callback' => array('tl_module_resource_booking', 'getResourceTypes'),
    'eval'             => array('multiple' => true, 'tl_class' => 'w50'),
    'sql'              => "blob NULL"
);

/**
 * Class tl_module_resource_booking
 */
class tl_module_resource_booking extends Contao\Backend
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
     * @return array
     */
    public function getResourceTypes()
    {
        $opt = array();
        $objDb = Contao\Database::getInstance()->prepare('SELECT * FROM tl_resource_booking_resource_type WHERE published=?')->execute('1');
        while ($objDb->next())
        {
            $opt[$objDb->id] = $objDb->title;
        }
        return $opt;
    }

}
