<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = array('tl_resource_booking_member', 'removeChildRecords');

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_booking_member extends Contao\Backend
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
        $this->Database->prepare('DELETE FROM tl_resource_booking WHERE member=?')->execute($dc->id);
    }

}
