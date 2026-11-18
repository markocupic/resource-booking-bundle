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

use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator;

/**
 * A slot: a time slot of a resource on a day, with the booking rules.
 *
 * Immutable value object without services: SlotFactory::create() collects the data
 * (time slot, resource, bookings, blocked period, logged-in member, current time),
 * the slot evaluates the rules. The same rules apply in the week calendar and to
 * booking requests: a slot is bookable, if all rules are met and enough items are
 * left for the requested items (1 in the week calendar).
 *
 * The slot contains no presentation: see WeekCalendar\SlotPresenter.
 */
final class Slot
{
    private const SECONDS_IN_DAY = 86400;

    /**
     * @param array<string, mixed>       $resource              Row of tl_resource_booking_resource
     * @param int                        $now                   Current time
     * @param int|null                   $loggedInMemberId      ID of the logged-in member, null for guests
     * @param int                        $requestedItems        1 in the week calendar, the requested items of a booking request
     * @param bool                       $isValidTimeSlot       The time slot exists, is published, belongs to the timetable of the resource and the times match (see ResourceBookingRestriction::isValidTimeSlot())
     * @param list<string>               $availableWeekdays     Weekdays on which the time slot is available, e.g. "monday"; empty: every weekday
     * @param int|null                   $blockedPeriodId       Blocked period covering this slot, e.g. holidays
     * @param string                     $blockedPeriodDescription Description of the blocked period (the title is internal)
     * @param int|null                   $weekStart             First day of the week of the slot
     * @param int|null                   $lastBookableWeekStart First day of the last week the resource can be booked (tl_resource_booking_resource.weeksAhead)
     * @param int|null                   $repeatUntilWeekStart  First day of the last week of the repetitions (booking request)
     * @param list<array<string, mixed>> $bookings              Rows of tl_resource_booking
     * @param array<string, mixed>       $bookingData           Data of the booking to save (see withBookingData())
     */
    public function __construct(
        public readonly int $timeSlotId,
        public readonly array $resource,
        public readonly int $startTime,
        public readonly int $endTime,
        public readonly int $now,
        public readonly int|null $loggedInMemberId = null,
        public readonly int $requestedItems = 1,
        public readonly bool $isValidTimeSlot = false,
        public readonly array $availableWeekdays = [],
        public readonly int|null $blockedPeriodId = null,
        public readonly string $blockedPeriodDescription = '',
        public readonly int|null $weekStart = null,
        public readonly int|null $lastBookableWeekStart = null,
        public readonly int|null $repeatUntilWeekStart = null,
        public readonly array $bookings = [],
        public readonly array $bookingData = [],
    ) {
    }

    /**
     * Returns a copy of the slot with the data of the booking to save (booking request).
     *
     * @param array<string, mixed> $bookingData
     */
    public function withBookingData(array $bookingData): self
    {
        return new self(
            timeSlotId: $this->timeSlotId,
            resource: $this->resource,
            startTime: $this->startTime,
            endTime: $this->endTime,
            now: $this->now,
            loggedInMemberId: $this->loggedInMemberId,
            requestedItems: $this->requestedItems,
            isValidTimeSlot: $this->isValidTimeSlot,
            availableWeekdays: $this->availableWeekdays,
            blockedPeriodId: $this->blockedPeriodId,
            blockedPeriodDescription: $this->blockedPeriodDescription,
            weekStart: $this->weekStart,
            lastBookableWeekStart: $this->lastBookableWeekStart,
            repeatUntilWeekStart: $this->repeatUntilWeekStart,
            bookings: $this->bookings,
            bookingData: $bookingData,
        );
    }

    public function getResourceId(): int
    {
        return (int) ($this->resource['id'] ?? 0);
    }

    public function isMemberLoggedIn(): bool
    {
        return null !== $this->loggedInMemberId;
    }

    /**
     * All rules are met and enough items are left for the requested items. Own bookings
     * of the logged-in member are not counted, a new booking replaces them.
     */
    public function isBookable(): bool
    {
        if (!$this->isValidTimeSlot || !$this->hasValidItemCount() || !$this->isInBookingWindow() || !$this->isAvailableOnWeekday() || $this->isInBlockedPeriod()) {
            return false;
        }

        return $this->canFulfillRequestedItems();
    }

    /**
     * The slot has not ended yet, lies within the repetitions and within the booking period.
     */
    public function isInBookingWindow(): bool
    {
        if ($this->endTime < $this->now) {
            return false;
        }

        if (null !== $this->repeatUntilWeekStart && $this->startTime > strtotime('+1 week', $this->repeatUntilWeekStart)) {
            return false;
        }

        return !$this->isOutsideBookingPeriod();
    }

    /**
     * Returns true if the slot lies after the booking period of the resource,
     * i.e. after the last week that can be booked (tl_resource_booking_resource.weeksAhead)
     * or after the "bookable until" date (tl_resource_booking_resource.bookableUntil).
     */
    public function isOutsideBookingPeriod(): bool
    {
        // The resource can only be booked a certain number of weeks ahead.
        if (null !== $this->weekStart && null !== $this->lastBookableWeekStart && $this->weekStart > $this->lastBookableWeekStart) {
            return true;
        }

        // The resource can only be booked up to and including the "bookable until" date.
        $bookableUntil = ResourceBookingRestriction::getBookableUntil($this->resource);

        return null !== $bookableUntil && $this->endTime > $bookableUntil + self::SECONDS_IN_DAY;
    }

    /**
     * Returns false if the time slot is not available on the weekday of this slot
     * (tl_resource_booking_time_slot.weekdays).
     */
    public function isAvailableOnWeekday(): bool
    {
        return [] === $this->availableWeekdays || \in_array(strtolower(date('l', $this->startTime)), $this->availableWeekdays, true);
    }

    /**
     * Returns true if the number of requested items is between 1 and the number
     * of available items of the resource.
     */
    public function hasValidItemCount(): bool
    {
        return BookingRequestValidator::isValidItemCount($this->requestedItems, $this->getItemsAvailable());
    }

    /**
     * Returns true if a blocked period (e.g. holidays) covers this slot.
     */
    public function isInBlockedPeriod(): bool
    {
        return null !== $this->blockedPeriodId;
    }

    /**
     * Enough items are left for the requested items (own bookings are not counted).
     */
    public function canFulfillRequestedItems(): bool
    {
        return $this->countBookedItems(excludeOwn: true) + $this->requestedItems <= $this->getItemsAvailable();
    }

    /**
     * Items that are not booked yet (bookings of all members).
     */
    public function getRemainingItems(): int
    {
        return $this->getItemsAvailable() - $this->countBookedItems();
    }

    public function hasBookings(): bool
    {
        return [] !== $this->bookings;
    }

    public function isFullyBooked(): bool
    {
        return $this->countBookedItems() >= $this->getItemsAvailable();
    }

    /**
     * The logged-in member has booked the slot and it has not ended yet.
     */
    public function isCancelable(): bool
    {
        return null !== $this->getMemberBooking() && $this->isInBookingWindow();
    }

    /**
     * The booking of the logged-in member (row of tl_resource_booking), or null.
     *
     * @return array<string, mixed>|null
     */
    public function getMemberBooking(): array|null
    {
        foreach ($this->bookings as $booking) {
            if ($this->isOwnedByLoggedInMember($booking)) {
                return $booking;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function isOwnedByLoggedInMember(array $booking): bool
    {
        return null !== $this->loggedInMemberId && $this->loggedInMemberId === (int) ($booking['member'] ?? -1);
    }

    private function countBookedItems(bool $excludeOwn = false): int
    {
        $total = 0;

        foreach ($this->bookings as $booking) {
            if ($excludeOwn && $this->isOwnedByLoggedInMember($booking)) {
                continue;
            }

            $total += (int) ($booking['itemsBooked'] ?? 1);
        }

        return $total;
    }

    private function getItemsAvailable(): int
    {
        return (int) ($this->resource['itemsAvailable'] ?? 0);
    }
}
