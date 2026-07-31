<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\ModuleModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCronJob('daily')]
class PurgePastBookingsCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        #[Autowire('%markocupic_resource_booking.purge_past_bookings_with_cron%')]
        private readonly bool $purgeOldBookingsWithCron,
        #[Autowire('%markocupic_resource_booking.apps%')]
        private readonly array $appConfigs,
        private readonly LoggerInterface|null $contaoCronLogger = null,
    ) {
    }

    /**
     * Delete old entries.
     */
    public function __invoke(): void
    {
        if (!$this->purgeOldBookingsWithCron) {
            return;
        }

        $intAffectedRows = 0;

        foreach ($this->getModuleIdsWithBookings() as $moduleId) {
            $appConfig = $this->resolveAppConfigForModule($moduleId);

            if (null === $appConfig) {
                continue;
            }

            $tstampLimit = $this->calculatePurgeLimit($appConfig);

            if (null === $tstampLimit) {
                continue;
            }

            $intAffectedRows += $this->deleteExpiredBookings($moduleId, $tstampLimit);
        }

        if ($intAffectedRows > 0) {
            $this->contaoCronLogger?->info(\sprintf('CRON: tl_resource_booking has been cleared from %s old entries.', $intAffectedRows));
        }
    }

    /**
     * Returns the distinct module ids that currently have bookings.
     *
     * @return list<int>
     */
    protected function getModuleIdsWithBookings(): array
    {
        // Select only the grouped column so the query is valid under ONLY_FULL_GROUP_BY.
        $rows = $this->connection->fetchAllAssociative('SELECT moduleId FROM tl_resource_booking GROUP BY moduleId');

        $moduleIds = [];

        foreach ($rows as $row) {
            $moduleId = (int) ($row['moduleId'] ?? 0);

            if ($moduleId > 0) {
                $moduleIds[] = $moduleId;
            }
        }

        return $moduleIds;
    }

    /**
     * Resolves the app configuration assigned to a module, or null if it cannot be resolved.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveAppConfigForModule(int $moduleId): array|null
    {
        $moduleAdapter = $this->framework->getAdapter(ModuleModel::class);

        if (null === ($objModule = $moduleAdapter->findById($moduleId))) {
            return null;
        }

        $strConfig = $objModule->resourceBooking_appConfig ?? null;

        if (null === $strConfig || !isset($this->appConfigs[$strConfig])) {
            return null;
        }

        return $this->appConfigs[$strConfig];
    }

    /**
     * Returns the timestamp before which bookings may be deleted, or null when
     * the app config does not enable back-week purging (intBackWeeks >= 0).
     *
     * @param array<string, mixed> $appConfig
     */
    protected function calculatePurgeLimit(array $appConfig): int|null
    {
        $intBackWeeks = (int) $appConfig['intBackWeeks'];

        // Only purge if bookings from past weeks are configured (negative value).
        if ($intBackWeeks >= 0) {
            return null;
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);

        $intWeeks = abs($intBackWeeks);
        $beginnWeek = $appConfig['beginnWeek'];
        $dateBeginnCurrentWeek = $dateAdapter->parse('d-m-Y', strtotime(\sprintf('%s this week', $beginnWeek)));

        $tstampLimit = strtotime($dateBeginnCurrentWeek.' -'.$intWeeks.' weeks');

        return false === $tstampLimit ? null : $tstampLimit;
    }

    protected function deleteExpiredBookings(int $moduleId, int $tstampLimit): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM tl_resource_booking WHERE moduleId = ? AND endTime < ?',
            [
                $moduleId,
                $tstampLimit,
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ],
        );
    }
}
