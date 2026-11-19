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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Restriction\TimeSlotRules;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deletes bookings that lie further back than the user can navigate
 * (tl_resource_booking_resource.weeksBack). The weeks are counted from the
 * first day of the week set in the timetable of the resource
 * (tl_resource_booking_time_slot_type.beginWeek).
 */
#[AsCronJob('daily')]
class PurgePastBookingsCron
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%markocupic_resource_booking.purge_past_bookings_with_cron%')]
        private readonly bool $purgeOldBookingsWithCron,
        private readonly LoggerInterface|null $contaoCronLogger = null,
        private readonly ClockInterface $clock = new NativeClock(),
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

        $affectedRows = 0;

        foreach ($this->getPurgeSettingsByResource() as $resourceId => $settings) {
            $tstampLimit = $this->calculatePurgeLimit($settings['weeksBack'], $settings['beginWeek']);

            if (null === $tstampLimit) {
                continue;
            }

            $affectedRows += $this->deleteExpiredBookings($resourceId, $tstampLimit);
        }

        if ($affectedRows > 0) {
            $this->contaoCronLogger?->info(\sprintf('CRON: tl_resource_booking has been cleared from %s old entries.', $affectedRows));
        }
    }

    /**
     * Returns the number of weeks back (tl_resource_booking_resource.weeksBack)
     * and the first day of the week of the timetable
     * (tl_resource_booking_time_slot_type.beginWeek) indexed by the resource id.
     *
     * @return array<int, array{weeksBack: int, beginWeek: string}>
     */
    protected function getPurgeSettingsByResource(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.id, r.weeksBack, t.beginWeek FROM tl_resource_booking_resource r LEFT JOIN tl_resource_booking_time_slot_type t ON t.id = r.timeSlotType',
        );

        $settings = [];

        foreach ($rows as $row) {
            $resourceId = (int) ($row['id'] ?? 0);

            if ($resourceId > 0) {
                $settings[$resourceId] = [
                    'weeksBack' => (int) ($row['weeksBack'] ?? 0),
                    'beginWeek' => TimeSlotRules::getBeginWeek($row),
                ];
            }
        }

        return $settings;
    }

    /**
     * Returns the timestamp before which bookings may be deleted, or null if
     * the user cannot navigate back (weeksBack = 0). In that case nothing is deleted.
     *
     * @throws \Exception
     */
    protected function calculatePurgeLimit(int $weeksBack, string $beginWeek = 'monday'): int|null
    {
        if ($weeksBack <= 0) {
            return null;
        }

        $currentWeek = DateHelper::getFirstDayOfWeek($beginWeek, $this->clock->now()->getTimestamp());

        return DateHelper::addWeeks(-$weeksBack, $currentWeek);
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
