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

namespace Markocupic\ResourceBookingBundle\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Symfony\Component\Clock\ClockInterface;

/**
 * Creates slots: collects the data of a slot (time slot, timetable, bookings,
 * blocked period, logged-in member, current time) and returns a new slot.
 */
class SlotFactory
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SlotDataProvider $dataProvider,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Fetches the bookings of the resource in the time range [from, to) with one query,
     * before the slots of e.g. a whole week are created.
     */
    public function preloadBookings(int $resourceId, int $from, int $to): void
    {
        $this->dataProvider->preloadBookings($resourceId, $from, $to);
    }

    /**
     * @param int      $requestedItems              1 in the week calendar, the requested items of a booking request
     * @param int|null $bookingRepeatStopWeekTstamp First day of the last week of the repetitions (booking request)
     *
     * @throws \Exception
     */
    public function create(int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $requestedItems = 1, int|null $bookingRepeatStopWeekTstamp = null): Slot
    {
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $now = $this->clock->now()->getTimestamp();
        $arrResource = $resource->row();

        $timeSlot = $this->dataProvider->getTimeSlot($timeSlotId);
        $timeSlotType = null === $timeSlot ? null : $this->dataProvider->getTimeSlotType((int) $timeSlot->pid);

        // The first day of the week is set in the timetable of the resource.
        $resourceTimeSlotType = null !== $timeSlotType && (int) $timeSlotType->id === (int) $resource->timeSlotType
            ? $timeSlotType
            : $this->dataProvider->getTimeSlotType((int) $resource->timeSlotType);
        $appConfig = ['beginWeek' => ResourceBookingRestriction::getBeginWeek($resourceTimeSlotType)];
        $firstDayOfWeek = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $startTime);
        $blockedPeriod = $this->findBlockedPeriod($arrResource, $startTime, $endTime, $timeSlotId);
        $member = $this->dataProvider->getLoggedInMember();

        return new Slot(
            [
                'timeSlotId' => $timeSlotId,
                // Weekdays on which the time slot is available (tl_resource_booking_time_slot.weekdays)
                'timeSlotWeekdays' => $timeSlot?->weekdays,
                // The time slot exists, is published, belongs to the (published) timetable of the resource
                // and the start and end time match the time slot (protection against manipulated requests)
                'isValidTimeSlot' => ResourceBookingRestriction::isValidTimeSlot($timeSlot?->row(), $timeSlotType?->row(), $arrResource, $startTime, $endTime),
                // Blocked period covering this slot, e.g. holidays (tl_resource_booking_blocked_period).
                // The title is an internal name and is not sent to the front end, only the description is displayed.
                'blockedPeriod' => null === $blockedPeriod ? null : ['id' => (int) $blockedPeriod['id']],
                'blockedPeriodDescription' => null === $blockedPeriod ? '' : trim(StringUtil::decodeEntities((string) ($blockedPeriod['description'] ?? ''))),
                'resource' => $arrResource,
                'pid' => (int) $resource->id,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'itemsBooked' => $requestedItems,
                // Timestamp of the "begin week weekday" (Monday by default)
                'bookingRepeatStopWeekTstamp' => $bookingRepeatStopWeekTstamp ?? $firstDayOfWeek,
                'weekday' => strtolower(date('l', $startTime)),
                'beginWeekTimestampSelectedWeek' => $firstDayOfWeek,
                // Timestamp of the first day of the last week the resource can be booked (tl_resource_booking_resource.weeksAhead)
                'lastPermittedWeekTstamp' => $dateHelperAdapter->addWeeksToTime(ResourceBookingRestriction::getWeeksAhead($resource), $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $now)),
                'bookings' => $this->dataProvider->getBookings((int) $resource->id, $startTime, $endTime),
            ],
            null === $member ? null : (int) $member->id,
            $now,
        );
    }

    /**
     * Returns the first published blocked period that covers the slot, or null.
     *
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>|null
     */
    private function findBlockedPeriod(array $resource, int $startTime, int $endTime, int $timeSlotId): array|null
    {
        foreach ($this->dataProvider->getBlockedPeriods() as $period) {
            if (ResourceBookingRestriction::isInBlockedPeriod($period, $resource, $startTime, $endTime, $timeSlotId)) {
                return $period;
            }
        }

        return null;
    }
}
