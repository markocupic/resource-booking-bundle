<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Cron;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Contao\Database;
use Contao\Date;
use Contao\System;

/**
 * @CronJob("daily")
 */
class Cron
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Delete old entries
     * Cronjob.
     */
    public function __invoke(): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (($intWeeks = (int) $configAdapter->get('rbb_intBackWeeks')) < 0) {
            $intWeeks = abs($intWeeks);
            $beginnWeek = $systemAdapter->getContainer()->getParameter('markocupic_resource_booking.beginnWeek');
            $dateMonThisWeek = $dateAdapter->parse('d-m-Y', strtotime(sprintf('%s this week', $beginnWeek)));

            if (false !== ($tstampLimit = strtotime($dateMonThisWeek.' -'.$intWeeks.' weeks'))) {
                $objStmt = $databaseAdapter->getInstance()->prepare('DELETE FROM tl_resource_booking WHERE endTime<?')->execute($tstampLimit);

                if (($intRows = $objStmt->affectedRows) > 0) {
                    $systemAdapter->log(sprintf('CRON: tl_resource_booking has been cleared from %s old entries.', $intRows), __METHOD__, TL_CRON);
                }
            }
        }
    }
}
