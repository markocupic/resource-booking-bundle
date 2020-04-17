<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\System;
use Contao\Config;

/**
 * Class Cron
 * @package Markocupic\ResourceBookingBundle\Cron
 */
class Cron
{
    /** @var ContaoFramework */
    private $framework;

    /**
     * Cron constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Delete old entries
     * Cronjob
     */
    public function deleteOldBookingsFromDb(): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (($intWeeks = (int) $configAdapter->get('rbb_intBackWeeks')) < 0)
        {
            $intWeeks = abs($intWeeks);
            $dateMonThisWeek = $dateAdapter->parse('d-m-Y', strtotime('monday this week'));
            if (($tstampLimit = strtotime($dateMonThisWeek . ' -' . $intWeeks . ' weeks')) !== false)
            {
                $objStmt = $databaseAdapter->getInstance()->prepare('DELETE FROM tl_resource_booking WHERE endTime<?')->execute($tstampLimit);
                if (($intRows = $objStmt->affectedRows) > 0)
                {
                    $systemAdapter->log(sprintf('CRON: tl_resource_booking has been cleaned from %s old entries.', $intRows), __METHOD__, TL_CRON);
                }
            }
        }
    }
}
