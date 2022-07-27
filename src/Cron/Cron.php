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

namespace Markocupic\ResourceBookingBundle\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Contao\Database;
use Contao\Date;
use Contao\ModuleModel;
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
     * Delete old entries.
     */
    public function __invoke(): void
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var ModuleModel $moduleAdapter */
        $moduleAdapter = $this->framework->getAdapter(ModuleModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Get all app configurations
        $arrAppConfigs = System::getContainer()->getParameter('markocupic_resource_booking.apps');

        $arrAppConfig = [];
        $intAffectedRows = 0;

        $objStmt = $databaseAdapter->getInstance()
            ->execute('SELECT * FROM tl_resource_booking GROUP BY moduleId')
        ;

        while ($objStmt->next()) {
            $moduleId = $objStmt->moduleId;

            if ((int) $moduleId > 0) {
                if (!isset($arrAppConfig[$moduleId])) {
                    if (null !== ($objModule = $moduleAdapter->findByPk($moduleId))) {
                        $strConfig = $objModule->resourceBooking_appConfig ?? null;

                        if (null !== $strConfig && isset($arrAppConfigs[$strConfig])) {
                            $arrAppConfig[$moduleId] = $arrAppConfigs[$strConfig];
                        }
                    }
                }

                if (isset($arrAppConfig[$moduleId])) {
                    $appConfig = $arrAppConfig[$moduleId];
                    $intWeeks = $appConfig['intBackWeeks'];

                    if ($intWeeks < 0) {
                        $intWeeks = abs($intWeeks);
                        $beginnWeek = $appConfig['beginnWeek'];
                        $dateBeginnCurrentWeek = $dateAdapter->parse('d-m-Y', strtotime(sprintf('%s this week', $beginnWeek)));

                        // Calculate the limit from which we can delete the entries
                        if (false !== ($tstampLimit = strtotime($dateBeginnCurrentWeek.' -'.$intWeeks.' weeks'))) {
                            $objStmtDel = $databaseAdapter->getInstance()
                                ->prepare('DELETE FROM tl_resource_booking WHERE moduleId=? AND endTime<?')
                                ->execute($moduleId, $tstampLimit)
                            ;

                            $intAffectedRows += $objStmtDel->affectedRows;
                        }
                    }
                }
            }
        }

        if ($intAffectedRows > 0) {
            $systemAdapter->log(sprintf('CRON: tl_resource_booking has been cleared from %s old entries.', $intAffectedRows), __METHOD__, TL_CRON);
        }
    }
}
