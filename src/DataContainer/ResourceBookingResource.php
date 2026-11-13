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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingResource
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_resource', target: 'list.sorting.child_record')]
    public function childRecordCallback(array $row): string
    {
        return '<div class="tl_content_left">'.$row['title'].'</div>';
    }

    /**
     * Shows a warning if future bookings of the resource no longer fit its booking
     * timetable (e.g. after another timetable or other weekdays have been selected).
     * Such bookings are not displayed in the week calendar anymore.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_resource', target: 'config.onsubmit')]
    public function warnAboutFutureBookingsNotMatchingTheTimetable(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $count = $this->countFutureBookingsNotMatchingTheTimetable((int) $dc->id);

        if ($count > 0) {
            $this->framework
                ->getAdapter(Message::class)
                ->addInfo($this->translator->trans('RBB.BE.futureBookingsNotMatchingTimetable', [$count], 'contao_default'))
            ;
        }
    }

    /**
     * Returns the number of future bookings of a resource that do not fit any published
     * time slot of its booking timetable (start time, end time and weekday) or that lie
     * on a weekday on which the resource cannot be booked.
     *
     * @throws Exception
     */
    public function countFutureBookingsNotMatchingTheTimetable(int $resourceId): int
    {
        $resource = $this->connection->fetchAssociative(
            'SELECT * FROM tl_resource_booking_resource WHERE id = ?',
            [$resourceId],
            [Types::INTEGER],
        );

        if (!$resource) {
            return 0;
        }

        $bookings = $this->connection->fetchAllAssociative(
            'SELECT id, startTime, endTime FROM tl_resource_booking WHERE pid = ? AND endTime > ?',
            [$resourceId, time()],
            [Types::INTEGER, Types::INTEGER],
        );

        if (empty($bookings)) {
            return 0;
        }

        // Published time slots of the published timetable (the same ones as in the week calendar)
        $timeSlots = $this->connection->fetchAllAssociative(
            'SELECT s.startTime, s.endTime, s.weekdays FROM tl_resource_booking_time_slot s
                INNER JOIN tl_resource_booking_time_slot_type t ON t.id = s.pid
                WHERE s.pid = ? AND s.published = 1 AND t.published = 1',
            [(int) $resource['timeSlotType']],
            [Types::INTEGER],
        );

        $count = 0;

        foreach ($bookings as $booking) {
            if (!$this->fitsTheTimetable($booking, $resource, $timeSlots)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed>             $booking
     * @param array<string, mixed>             $resource
     * @param array<int, array<string, mixed>> $timeSlots
     */
    private function fitsTheTimetable(array $booking, array $resource, array $timeSlots): bool
    {
        $startTime = (int) $booking['startTime'];
        $endTime = (int) $booking['endTime'];
        $weekday = strtolower(date('l', $startTime));

        if (!ResourceBookingRestriction::isWeekdayAllowed($resource, $weekday)) {
            return false;
        }

        // The week calendar adds the time of the time slot (seconds) to the midnight of the day.
        $midnight = strtotime(date('Y-m-d', $startTime));

        foreach ($timeSlots as $timeSlot) {
            if ((int) $timeSlot['startTime'] !== $startTime - $midnight || (int) $timeSlot['endTime'] !== $endTime - $midnight) {
                continue;
            }

            if (ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($timeSlot, $weekday)) {
                return true;
            }
        }

        return false;
    }
}
