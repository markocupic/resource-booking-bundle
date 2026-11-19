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

use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Restriction\BlockedPeriod;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;
use Markocupic\ResourceBookingBundle\Restriction\TimeSlotRules;
use Markocupic\ResourceBookingBundle\Util\CsvList;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\Clock\ClockInterface;

/**
 * Creates slots: collects the data of a slot (time slot, timetable, bookings,
 * blocked period, logged-in member, current time) and returns a new slot.
 */
class SlotFactory
{
    public function __construct(
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
     * @param int      $requestedItems       1 in the week calendar, the requested items of a booking request
     * @param int|null $repeatUntilWeekStart First day of the last week of the repetitions (booking request)
     *
     * @throws \Exception
     */
    public function create(int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $requestedItems = 1, int|null $repeatUntilWeekStart = null): Slot
    {
        $now = $this->clock->now()->getTimestamp();
        $resourceRules = ResourceRules::fromModel($resource);

        $timeSlot = $this->dataProvider->getTimeSlot($timeSlotId);
        $timeSlotType = null === $timeSlot ? null : $this->dataProvider->getTimeSlotType((int) $timeSlot->pid);

        // The first day of the week is set in the timetable of the resource.
        $resourceTimeSlotType = null !== $timeSlotType && (int) $timeSlotType->id === (int) $resource->timeSlotType
            ? $timeSlotType
            : $this->dataProvider->getTimeSlotType((int) $resource->timeSlotType);
        $beginWeek = TimeSlotRules::getBeginWeek($resourceTimeSlotType);
        $firstDayOfWeek = DateHelper::getFirstDayOfWeek($beginWeek, $startTime);
        $blockedPeriod = $this->findBlockedPeriod($resourceRules, $startTime, $endTime, $timeSlotId);
        $member = $this->dataProvider->getLoggedInMember();

        return new Slot(
            timeSlotId: $timeSlotId,
            resource: $resourceRules,
            startTime: $startTime,
            endTime: $endTime,
            now: $now,
            loggedInMemberId: null === $member ? null : (int) $member->id,
            requestedItems: $requestedItems,
            isValidTimeSlot: TimeSlotRules::isValidTimeSlot($timeSlot?->row(), $timeSlotType?->row(), $resourceRules, $startTime, $endTime),
            // tl_resource_booking_time_slot.weekdays, empty: every weekday
            availableWeekdays: CsvList::parse($timeSlot?->weekdays),
            blockedPeriodId: $blockedPeriod?->id,
            // The title of the blocked period is an internal name, only the description is displayed
            blockedPeriodDescription: null === $blockedPeriod ? '' : trim(StringUtil::decodeEntities($blockedPeriod->description)),
            weekStart: $firstDayOfWeek,
            lastBookableWeekStart: DateHelper::addWeeks($resourceRules->weeksAhead, DateHelper::getFirstDayOfWeek($beginWeek, $now)),
            // Without repetitions: the week of the slot
            repeatUntilWeekStart: $repeatUntilWeekStart ?? $firstDayOfWeek,
            bookings: $this->dataProvider->getBookings((int) $resource->id, $startTime, $endTime),
        );
    }

    /**
     * Returns the first published blocked period that covers the slot, or null.
     */
    private function findBlockedPeriod(ResourceRules $resource, int $startTime, int $endTime, int $timeSlotId): BlockedPeriod|null
    {
        foreach ($this->dataProvider->getBlockedPeriods() as $period) {
            if ($period->covers($resource, $startTime, $endTime, $timeSlotId)) {
                return $period;
            }
        }

        return null;
    }
}
