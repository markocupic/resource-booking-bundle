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

namespace Markocupic\ResourceBookingBundle\DataContainer;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\Input;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingBlockedPeriod
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Resources grouped by resource type.
     *
     * @throws Exception
     *
     * @return array<string, array<int, string>>
     */
    #[AsCallback(table: 'tl_resource_booking_blocked_period', target: 'fields.resources.options')]
    public function getResourceOptions(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.id, r.title, t.title AS typeTitle FROM tl_resource_booking_resource r
                LEFT JOIN tl_resource_booking_resource_type t ON t.id = r.pid
                ORDER BY t.title, r.title',
        );

        $options = [];

        foreach ($rows as $row) {
            $options[(string) ($row['typeTitle'] ?? '-')][(int) $row['id']] = (string) $row['title'];
        }

        return $options;
    }

    #[AsCallback(table: 'tl_resource_booking_blocked_period', target: 'list.label.label')]
    public function listLabel(array $row, string $label, DataContainer $dc, array $columns): array
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateFormat = (string) $this->framework->getAdapter(Config::class)->get('dateFormat');

        $columns[1] = $dateAdapter->parse($dateFormat, (int) $row['dateFrom']);
        $columns[2] = $dateAdapter->parse($dateFormat, (int) $row['dateTo']);

        $timeFrom = ResourceBookingRestriction::timeToSeconds($row['timeFrom'] ?? null);
        $timeTo = ResourceBookingRestriction::timeToSeconds($row['timeTo'] ?? null);

        if (!empty($row['addTimeRange']) && null !== $timeFrom && null !== $timeTo) {
            $columns[0] .= \sprintf(' <span class="tl_gray">(%s - %s)</span>', gmdate('H:i', $timeFrom), gmdate('H:i', $timeTo));
        }

        return $columns;
    }

    /**
     * The end time has to be after the start time.
     */
    #[AsCallback(table: 'tl_resource_booking_blocked_period', target: 'fields.timeTo.save')]
    public function validateTimeTo(mixed $value): mixed
    {
        // The posted start time ("hh:mm") and the converted end time (Contao time value)
        $timeFrom = ResourceBookingRestriction::timeToSeconds($this->framework->getAdapter(Input::class)->post('timeFrom'));
        $timeTo = ResourceBookingRestriction::timeToSeconds($value);

        if (null !== $timeFrom && null !== $timeTo && $timeTo <= $timeFrom) {
            throw new \RuntimeException($this->translator->trans('RBB.BE.timeToBeforeTimeFrom', [], 'contao_default'));
        }

        return $value;
    }

    /**
     * Swaps reversed dates and shows a warning if existing future bookings
     * fall into the blocked period. These bookings are not deleted.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_blocked_period', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $period = $this->connection->fetchAssociative(
            'SELECT * FROM tl_resource_booking_blocked_period WHERE id = ?',
            [(int) $dc->id],
            [Types::INTEGER],
        );

        if (!$period) {
            return;
        }

        if ((int) $period['dateTo'] < (int) $period['dateFrom']) {
            [$period['dateFrom'], $period['dateTo']] = [$period['dateTo'], $period['dateFrom']];

            $this->connection->update(
                'tl_resource_booking_blocked_period',
                ['dateFrom' => $period['dateFrom'], 'dateTo' => $period['dateTo']],
                ['id' => (int) $period['id']],
            );
        }

        $count = $this->countFutureBookingsInBlockedPeriod($period);

        if ($count > 0) {
            $this->framework
                ->getAdapter(Message::class)
                ->addInfo($this->translator->trans('RBB.BE.bookingsInBlockedPeriod', [$count], 'contao_default'))
            ;
        }
    }

    /**
     * Returns the number of future bookings (not blocked slots) that fall into the blocked period.
     *
     * @param array<string, mixed> $period
     *
     * @throws Exception
     */
    public function countFutureBookingsInBlockedPeriod(array $period): int
    {
        if (empty($period['published'])) {
            return 0;
        }

        $bookings = $this->connection->fetchAllAssociative(
            'SELECT b.startTime, b.endTime, r.id, r.pid FROM tl_resource_booking b
                INNER JOIN tl_resource_booking_resource r ON r.id = b.pid
                WHERE b.isBlocked = 0 AND b.endTime > ? AND b.startTime < ? AND b.endTime > ?',
            [time(), strtotime('+1 day', (int) $period['dateTo']), (int) $period['dateFrom']],
            [Types::INTEGER, Types::INTEGER, Types::INTEGER],
        );

        $count = 0;

        foreach ($bookings as $booking) {
            $resource = ['id' => $booking['id'], 'pid' => $booking['pid']];

            if (ResourceBookingRestriction::isInBlockedPeriod($period, $resource, (int) $booking['startTime'], (int) $booking['endTime'])) {
                ++$count;
            }
        }

        return $count;
    }
}
