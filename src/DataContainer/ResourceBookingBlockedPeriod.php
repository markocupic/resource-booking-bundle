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
use Contao\Message;
use Contao\StringUtil;
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
        $columns[2] = empty($row['dateTo'])
            ? $this->translator->trans('tl_resource_booking_blocked_period.unlimited', [], 'contao_tl_resource_booking_blocked_period')
            : $dateAdapter->parse($dateFormat, (int) $row['dateTo']);

        $summary = [];

        foreach (ResourceBookingRestriction::getBlockedTimes($row) as $blockedTime) {
            $text = 'daily' === $blockedTime['weekday']
                ? $this->translator->trans('tl_resource_booking_blocked_period.weekdayOptions.daily', [], 'contao_tl_resource_booking_blocked_period')
                : $this->translator->trans('MSC.DAYS_SHORTENED.'.$blockedTime['weekday'], [], 'contao_default');

            if (0 !== $blockedTime['timeFrom'] || 86400 !== $blockedTime['timeTo']) {
                $text .= ' '.self::formatTime($blockedTime['timeFrom']).'–'.self::formatTime($blockedTime['timeTo']);
            }

            $summary[] = $text;
        }

        if (!empty($summary)) {
            $columns[0] .= ' <span class="tl_gray">('.implode(', ', $summary).')</span>';
        }

        return $columns;
    }

    /**
     * Shows an empty field instead of 01.01.1970 if no date is set (dateTo = unlimited).
     */
    #[AsCallback(table: 'tl_resource_booking_blocked_period', target: 'fields.dateTo.load')]
    public function loadDateTo(mixed $value): mixed
    {
        return empty($value) ? '' : $value;
    }

    /**
     * Validates the blocked times (row wizard): the times must be empty or "hh:mm"
     * (00:00 - 24:00) and the end time must be after the start time.
     */
    #[AsCallback(table: 'tl_resource_booking_blocked_period', target: 'fields.blockedTimes.save')]
    public function validateBlockedTimes(mixed $value): mixed
    {
        $rows = \is_array($value) ? $value : StringUtil::deserialize($value, true);

        foreach (array_values($rows) as $i => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $timeFrom = trim((string) ($row['timeFrom'] ?? ''));
            $timeTo = trim((string) ($row['timeTo'] ?? ''));

            $from = '' === $timeFrom ? 0 : self::parseTime($timeFrom);
            $to = '' === $timeTo ? 86400 : self::parseTime($timeTo);

            if (null === $from || null === $to) {
                throw new \RuntimeException($this->translator->trans('RBB.BE.invalidBlockedTime', [$i + 1], 'contao_default'));
            }

            if ($to <= $from) {
                throw new \RuntimeException($this->translator->trans('RBB.BE.timeToBeforeTimeFrom', [$i + 1], 'contao_default'));
            }
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

        if (!empty($period['dateTo']) && (int) $period['dateTo'] < (int) $period['dateFrom']) {
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
            [time(), empty($period['dateTo']) ? PHP_INT_MAX : strtotime('+1 day', (int) $period['dateTo']), (int) $period['dateFrom']],
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

    /**
     * Converts "hh:mm" (00:00 - 24:00) into seconds since midnight.
     */
    private static function parseTime(string $time): int|null
    {
        if (!preg_match('/^([01]?\d|2[0-4]):([0-5]\d)$/', $time, $matches)) {
            return null;
        }

        $seconds = (int) $matches[1] * 3600 + (int) $matches[2] * 60;

        return $seconds <= 86400 ? $seconds : null;
    }

    private static function formatTime(int $seconds): string
    {
        return 86400 === $seconds ? '24:00' : gmdate('H:i', $seconds);
    }
}
