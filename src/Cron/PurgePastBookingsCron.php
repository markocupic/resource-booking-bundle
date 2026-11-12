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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deletes bookings that lie further back than the user can navigate
 * (tl_resource_booking_resource.weeksBack).
 */
#[AsCronJob('daily')]
class PurgePastBookingsCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        #[Autowire('%markocupic_resource_booking.purge_past_bookings_with_cron%')]
        private readonly bool $purgeOldBookingsWithCron,
        #[Autowire('%markocupic_resource_booking.apps%')]
        private readonly array $appConfigs = [],
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

        foreach ($this->getWeeksBackByResource() as $resourceId => $weeksBack) {
            $tstampLimit = $this->calculatePurgeLimit($weeksBack);

            if (null === $tstampLimit) {
                continue;
            }

            $intAffectedRows += $this->deleteExpiredBookings($resourceId, $tstampLimit);
        }

        if ($intAffectedRows > 0) {
            $this->contaoCronLogger?->info(\sprintf('CRON: tl_resource_booking has been cleared from %s old entries.', $intAffectedRows));
        }
    }

    /**
     * Returns the number of weeks back (tl_resource_booking_resource.weeksBack) indexed by the resource id.
     *
     * @return array<int, int>
     */
    protected function getWeeksBackByResource(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, weeksBack FROM tl_resource_booking_resource');

        $weeksBack = [];

        foreach ($rows as $row) {
            $resourceId = (int) ($row['id'] ?? 0);

            if ($resourceId > 0) {
                $weeksBack[$resourceId] = (int) ($row['weeksBack'] ?? 0);
            }
        }

        return $weeksBack;
    }

    /**
     * Returns the timestamp before which bookings may be deleted, or null if
     * the user cannot navigate back (weeksBack = 0). In that case nothing is deleted.
     */
    protected function calculatePurgeLimit(int $weeksBack): int|null
    {
        if ($weeksBack <= 0) {
            return null;
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);

        $dateBeginCurrentWeek = $dateAdapter->parse('d-m-Y', strtotime(\sprintf('%s this week', $this->getBeginWeek())));

        $tstampLimit = strtotime($dateBeginCurrentWeek.' -'.$weeksBack.' weeks');

        return false === $tstampLimit ? null : $tstampLimit;
    }

    /**
     * Resources are not assigned to an app configuration. The week begin of the
     * "default" app configuration (or the first one) is used to compute the limit.
     */
    protected function getBeginWeek(): string
    {
        // reset() cannot be used here, because the property is readonly.
        $appConfig = $this->appConfigs['default'] ?? array_values($this->appConfigs)[0] ?? [];

        return (string) ($appConfig['begin_week'] ?? 'monday');
    }

    protected function deleteExpiredBookings(int $resourceId, int $tstampLimit): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM tl_resource_booking WHERE pid = ? AND endTime < ?',
            [
                $resourceId,
                $tstampLimit,
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ],
        );
    }
}
