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

namespace Markocupic\ResourceBookingBundle\Backend;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekTableBuilder;

/**
 * Week view of a resource for the back end widget "rbbBlockedSlots": the time slots
 * of the resource with their bookings and the other blocked periods covering them.
 */
class BlockedSlotCalendar
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param int $tstamp   Any time within the week
     * @param int $periodId The blocked period being edited (ignored when looking for other blocked periods)
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getWeek(ResourceBookingResourceModel $resource, int $tstamp, int $periodId = 0): array
    {
        $this->framework->initialize();

        $timeSlotType = $this->framework->getAdapter(ResourceBookingTimeSlotTypeModel::class)->findById((int) $resource->timeSlotType);
        $beginWeek = ResourceBookingRestriction::getBeginWeek($timeSlotType);
        $weekStart = DateHelper::getFirstDayOfWeek(['beginWeek' => $beginWeek], $tstamp);
        $daysOfWeek = self::getDaysOfWeek($beginWeek);

        $timeSlots = $this->getPublishedTimeSlots($resource);
        $visibleWeekdays = WeekTableBuilder::getVisibleWeekdays($timeSlots, $daysOfWeek);
        $bookings = $this->getBookings((int) $resource->id, $weekStart, strtotime('+8 days', $weekStart));
        $otherPeriods = array_filter(
            $this->framework->getAdapter(ResourceBookingBlockedPeriodModel::class)->findPublishedRows(),
            static fn (array $period): bool => (int) $period['id'] !== $periodId,
        );

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateFormat = (string) $this->framework->getAdapter(Config::class)->get('dateFormat');

        $days = [];

        foreach ($daysOfWeek as $dayIndex => $weekday) {
            if (!\in_array($weekday, $visibleWeekdays, true)) {
                continue;
            }

            $dayStart = strtotime(\sprintf('+%s day', $dayIndex), $weekStart);
            $days[$dayIndex] = [
                'weekday' => $weekday,
                'label' => $dateAdapter->parse('D', $dayStart).' '.$dateAdapter->parse($dateFormat, $dayStart),
                // Two lines in the table header (narrow columns on small screens)
                'weekdayLabel' => $dateAdapter->parse('D', $dayStart),
                'dateLabel' => $dateAdapter->parse($dateFormat, $dayStart),
            ];
        }

        $rows = [];

        foreach ($timeSlots as $timeSlot) {
            if (!WeekTableBuilder::isTimeSlotDisplayed($timeSlot, $visibleWeekdays)) {
                continue;
            }

            $cells = [];
            $rowLabel = self::getTimeSlotLabel($timeSlot);

            foreach (array_keys($days) as $dayIndex) {
                $weekday = $daysOfWeek[$dayIndex];

                if (!ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($timeSlot, $weekday)) {
                    $cells[] = ['available' => false];
                    continue;
                }

                // Same calculation as in the week calendar (WeekTableBuilder)
                $dayStart = strtotime(\sprintf('+%s day', $dayIndex), $weekStart);
                $startTime = $dayStart + (int) $timeSlot['startTime'];
                $endTime = $dayStart + (int) $timeSlot['endTime'];
                $slotBookings = $bookings[$startTime.':'.$endTime] ?? ['count' => 0, 'items' => 0];

                $blockedBy = [];

                foreach ($otherPeriods as $period) {
                    if (ResourceBookingRestriction::isInBlockedPeriod($period, $resource->row(), $startTime, $endTime, (int) $timeSlot['id'])) {
                        // Title (internal name), otherwise the note of the week calendar or the ID
                        $blockedBy[] = trim((string) $period['title']) ?: (trim((string) ($period['description'] ?? '')) ?: '#'.$period['id']);
                    }
                }

                $cells[] = [
                    'available' => true,
                    'value' => BlockedSlots::key(['timeSlot' => (int) $timeSlot['id'], 'date' => date('Y-m-d', $dayStart)]),
                    // E.g. "Mi 14.06.2023, 08:00 – 09:00", for the warning about blocked slots with bookings
                    'label' => $days[$dayIndex]['label'].', '.$rowLabel,
                    'isPast' => $endTime <= time(),
                    'bookingCount' => $slotBookings['count'],
                    'itemsBooked' => $slotBookings['items'],
                    'blockedBy' => $blockedBy,
                ];
            }

            $rows[] = [
                'label' => $rowLabel,
                'cells' => $cells,
            ];
        }

        $weekEnd = strtotime('+6 days', $weekStart);

        return [
            'resource' => [
                'id' => (int) $resource->id,
                'title' => (string) $resource->title,
                'itemsAvailable' => max(1, (int) $resource->itemsAvailable),
            ],
            'week' => [
                'start' => $weekStart,
                'prev' => strtotime('-1 week', $weekStart),
                'next' => strtotime('+1 week', $weekStart),
                'label' => $dateAdapter->parse($dateFormat, $weekStart).' – '.$dateAdapter->parse($dateFormat, $weekEnd),
                // ISO calendar week of the Thursday (the week may start on another day than Monday)
                'number' => (int) date('W', strtotime('+3 days', $weekStart)),
                'year' => (int) date('o', strtotime('+3 days', $weekStart)),
            ],
            'days' => array_values($days),
            'rows' => $rows,
        ];
    }

    /**
     * The blocked slots that have bookings which have not ended yet, e.g. to warn about
     * them in the back end, also if they are not in the displayed week.
     *
     * @param list<array{timeSlot: int, date: string}> $slots
     *
     * @return array<string, array{bookingCount: int, label: string}> Indexed by BlockedSlots::key()
     */
    public function getSlotsWithBookings(ResourceBookingResourceModel $resource, array $slots): array
    {
        $this->framework->initialize();

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateFormat = (string) $this->framework->getAdapter(Config::class)->get('dateFormat');
        $timeSlotModel = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);
        $result = [];

        foreach ($slots as $slot) {
            $dayStart = (int) strtotime($slot['date']);

            // Bookings of the time slot on this day (the times are read from the time slot)
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM tl_resource_booking WHERE pid = ? AND timeSlotId = ? AND startTime >= ? AND startTime < ? AND endTime > ?',
                [(int) $resource->id, $slot['timeSlot'], $dayStart, strtotime('+1 day', $dayStart), time()],
            );

            if ($count < 1) {
                continue;
            }

            $timeSlot = $timeSlotModel->findById($slot['timeSlot']);

            $result[BlockedSlots::key($slot)] = [
                'bookingCount' => $count,
                'label' => $dateAdapter->parse('D', $dayStart).' '.$dateAdapter->parse($dateFormat, $dayStart).(null === $timeSlot ? '' : ', '.self::getTimeSlotLabel($timeSlot->row())),
            ];
        }

        return $result;
    }

    /**
     * Labels of all time slots of the timetable of the resource ("08:00 – 09:00"),
     * indexed by the ID, also of unpublished time slots.
     *
     * @return array<int, string>
     */
    public function getTimeSlotLabels(ResourceBookingResourceModel $resource): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, startTime, endTime FROM tl_resource_booking_time_slot WHERE pid = ? ORDER BY sorting',
            [(int) $resource->timeSlotType],
        );

        $labels = [];

        foreach ($rows as $row) {
            $labels[(int) $row['id']] = self::getTimeSlotLabel($row);
        }

        return $labels;
    }

    /**
     * The blocked slots of the other blocked periods in the mode "slots" of the resource
     * that have not passed yet (read only in the back end widget).
     *
     * @return list<array{timeSlot: int, date: string, title: string, published: bool}>
     */
    public function getOtherBlockedSlots(ResourceBookingResourceModel $resource, int $periodId): array
    {
        $periods = $this->connection->fetchAllAssociative(
            'SELECT id, tstamp, title, description, published, blockedSlots FROM tl_resource_booking_blocked_period WHERE mode = ? AND resource = ? AND id != ?',
            [ResourceBookingBlockedPeriodModel::MODE_SLOTS, (int) $resource->id, $periodId],
        );

        $today = date('Y-m-d');
        $result = [];

        foreach ($periods as $period) {
            $title = trim((string) $period['title']) ?: (trim((string) $period['description']) ?: '#'.$period['id']);

            foreach (BlockedSlots::fromPeriod($period) as $slot) {
                if ($slot['date'] >= $today) {
                    $result[] = [...$slot, 'title' => $title, 'published' => (bool) $period['published']];
                }
            }
        }

        usort($result, static fn (array $a, array $b): int => [$a['date'], $a['timeSlot']] <=> [$b['date'], $b['timeSlot']]);

        return $result;
    }

    /**
     * @param array<string, mixed> $timeSlot
     */
    public static function getTimeSlotLabel(array $timeSlot): string
    {
        return UtcTimeHelper::parseStartTime((int) $timeSlot['startTime']).' – '.UtcTimeHelper::parseEndTime((int) $timeSlot['endTime']);
    }

    /**
     * All seven weekdays, starting with the first day of the week of the timetable.
     *
     * @return list<string>
     */
    public static function getDaysOfWeek(string $beginWeek): array
    {
        $weekdays = [...RbbConfig::RBB_WEEKDAYS, ...RbbConfig::RBB_WEEKDAYS];
        $offset = (int) array_search($beginWeek, $weekdays, true);

        return \array_slice($weekdays, $offset, 7);
    }

    /**
     * Returns the slots whose time slot belongs to the timetable of the resource.
     * Other slots are removed, e.g. after the timetable of the resource has been
     * replaced or if the request has been manipulated.
     *
     * @param list<array{timeSlot: int, date: string}> $slots
     *
     * @return list<array{timeSlot: int, date: string}>
     */
    public function filterValidSlots(ResourceBookingResourceModel $resource, array $slots): array
    {
        $this->framework->initialize();

        $timeSlotModel = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);
        $timeSlotTypeId = (int) $resource->timeSlotType;
        $cache = [];

        return array_values(array_filter(
            $slots,
            static function (array $slot) use ($timeSlotModel, $timeSlotTypeId, &$cache): bool {
                $cache[$slot['timeSlot']] ??= (int) ($timeSlotModel->findById($slot['timeSlot'])?->pid ?? 0);

                return $timeSlotTypeId > 0 && $cache[$slot['timeSlot']] === $timeSlotTypeId;
            },
        ));
    }

    /**
     * Number of bookings and booked items per slot ("<startTime>:<endTime>").
     *
     * @return array<string, array{count: int, items: int}>
     */
    private function getBookings(int $resourceId, int $from, int $to): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT startTime, endTime, COUNT(*) AS bookingCount, SUM(itemsBooked) AS items FROM tl_resource_booking
                WHERE pid = ? AND startTime >= ? AND startTime < ? GROUP BY startTime, endTime',
            [$resourceId, $from, $to],
        );

        $bookings = [];

        foreach ($rows as $row) {
            $bookings[$row['startTime'].':'.$row['endTime']] = ['count' => (int) $row['bookingCount'], 'items' => (int) $row['items']];
        }

        return $bookings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPublishedTimeSlots(ResourceBookingResourceModel $resource): array
    {
        $collection = $this->framework
            ->getAdapter(ResourceBookingTimeSlotModel::class)
            ->findPublishedByPid((int) $resource->timeSlotType)
        ;

        $timeSlots = [];

        if (null !== $collection) {
            foreach ($collection as $timeSlot) {
                $timeSlots[] = $timeSlot->row();
            }
        }

        return $timeSlots;
    }
}
