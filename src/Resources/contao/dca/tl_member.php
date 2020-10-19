<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

use Contao\Backend;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = array('tl_resource_booking_member', 'removeChildRecords');

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_booking_member extends Backend
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
	 * @param DataContainer $dc
	 */
	public function removeChildRecords(DataContainer $dc)
	{
		if (!$dc->id)
		{
			return;
		}
		// Delete child bookings
		$this->Database->prepare('DELETE FROM tl_resource_booking WHERE member=?')->execute($dc->id);
	}
}
