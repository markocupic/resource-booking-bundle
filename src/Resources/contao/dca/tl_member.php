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

$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = ['tl_resource_booking_member', 'removeChildRecords'];

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 */
class tl_resource_booking_member extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');
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
        $this->Database->prepare('DELETE FROM tl_resource_booking WHERE member=?')->execute($dc->id);
    }
}
