<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Database;
use Contao\Date;
use Contao\System;
use Contao\Config;

/**
 * Class Cron
 * @package Markocupic\ResourceBookingBundle
 */
class Cron
{

    /**
     * Delete old entries
     * Cronjob
     */
    public function deleteOldBookingsFromDb(): void
    {
        if (($intWeeks = (int)Config::get('rbb_intBackWeeks')) < 0)
        {
            $intWeeks = abs($intWeeks);
            $dateMonThisWeek = Date::parse('d-m-Y', strtotime('monday this week'));
            if (($tstampLimit = strtotime($dateMonThisWeek . ' -' . $intWeeks . ' weeks')) !== false)
            {
                $objStmt = Database::getInstance()->prepare('DELETE FROM tl_resource_booking WHERE endTime<?')->execute($tstampLimit);
                if (($intRows = $objStmt->affectedRows) > 0)
                {
                    System::log(sprintf('CRON: tl_resource_booking has been cleaned from %s old entries.', $intRows), __METHOD__, TL_CRON);
                }
            }
        }
    }
}
