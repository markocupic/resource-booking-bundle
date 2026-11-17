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
 * A slot: a time slot of a resource on a day, with the evaluated booking rules.
 *
 * Immutable value object without services: SlotFactory::create() collects the data
 * (time slot, resource, bookings, blocked period, logged-in member, current time),
 * the slot evaluates the rules. The same rules apply in the week calendar and to
 * booking requests: a slot is bookable, if all rules are met and enough items are
 * left for the requested items (1 in the week calendar).
 *
 * The slot contains no presentation (texts, dates, CSS IDs): see
 * WeekCalendar\SlotPresenter.
 *
 * Input data (see SlotFactory::create()):
 *
 * @property int                        $timeSlotId
 * @property string|null                $timeSlotWeekdays               Serialized weekdays of the time slot
 * @property bool                       $isValidTimeSlot
 * @property array{id: int}|null        $blockedPeriod
 * @property string                     $blockedPeriodDescription
 * @property array<string, mixed>       $resource
 * @property int                        $pid
 * @property int                        $startTime
 * @property int                        $endTime
 * @property int                        $itemsBooked                    Requested items
 * @property int                        $bookingRepeatStopWeekTstamp
 * @property string                     $weekday
 * @property int                        $beginWeekTimestampSelectedWeek
 * @property int                        $lastPermittedWeekTstamp
 * @property list<array<string, mixed>> $bookings                       Rows of tl_resource_booking
 * @property array<string, mixed>       $dataBooking                    Data of the booking to save (see withBookingData())
 *
 * Results of the rules (see row()):
 * @property bool                      $userIsLoggedIn
 * @property bool                      $hasValidItemCount
 * @property bool                      $isInBlockedPeriod
 * @property bool                      $isWithinAllowedDateRange
 * @property bool                      $isTimeSlotAvailable
 * @property bool                      $isBookable
 * @property bool                      $enoughItemsAvailable
 * @property int                       $itemsStillAvailable
 * @property bool                      $isFullyBooked
 * @property bool                      $hasBookings
 * @property int                       $bookingCount
 * @property bool                      $userHasBooked
 * @property array<string, mixed>|null $userBooking
 * @property bool                      $isCancelable
 */
final class Slot
{
    private const SECONDS_IN_DAY = 86400;

    /**
     * Input data and the results of the rules.
     *
     * @var array<string, mixed>
     */
    private readonly array $data;

    /**
     * @param array<string, mixed> $data             Input data (see the properties above)
     * @param int|null             $loggedInMemberId ID of the logged-in member, null for guests
     * @param int                  $now              Current time
     */
    public function __construct(
        array $data,
        private readonly int|null $loggedInMemberId,
        private readonly int $now,
    ) {
        $data['bookings'] ??= [];
        $data['dataBooking'] ??= [];
        $this->data = $data;
    }

    public function __get(string $key): mixed
    {
        // Input data directly, the results of the rules are only evaluated if needed
        if (\array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return $this->row()[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return null !== $this->__get($key);
    }

    /**
     * Returns a copy of the slot with the data of the booking to save (booking request).
     *
     * @param array<string, mixed> $dataBooking
     */
    public function withBookingData(array $dataBooking): self
    {
        return new self([...$this->data, 'dataBooking' => $dataBooking], $this->loggedInMemberId, $this->now);
    }

    /**
     * The input data and the results of the rules, e.g. for the front end.
     *
     * @return array<string, mixed>
     */
    public function row(): array
    {
        return [
            ...$this->data,
            'userIsLoggedIn' => null !== $this->loggedInMemberId,
            'hasValidItemCount' => $this->hasValidItemCount(),
            'isInBlockedPeriod' => $this->isInBlockedPeriod(),
            'isWithinAllowedDateRange' => $this->isWithinAllowedDateRange(),
            'isTimeSlotAvailable' => $this->isTimeSlotAvailable(),
            'isBookable' => $this->isBookable(),
            'enoughItemsAvailable' => $this->canFulfillRequestedItems(),
            'itemsStillAvailable' => $this->getRemainingItems(),
            'isFullyBooked' => $this->isFullyBooked(),
            'hasBookings' => $this->hasBookings(),
            'bookingCount' => \count($this->getBookings()),
            'userHasBooked' => null !== $this->getUserBooking(),
            'userBooking' => $this->getUserBooking(),
            'isCancelable' => $this->isCancelable(),
        ];
    }

    /**
     * All rules are met and enough items are left for the requested items. Own bookings
     * of the logged-in member are not counted, a new booking replaces them.
     */
    public function isBookable(): bool
    {
        if (!$this->isValidTimeSlot() || !$this->hasValidItemCount() || !$this->isWithinAllowedDateRange() || !$this->isTimeSlotAvailable() || $this->isInBlockedPeriod()) {
            return false;
        }

        return $this->canFulfillRequestedItems();
    }

    /**
     * The slot has not ended yet, lies within the repetitions and within the booking period.
     */
    public function isWithinAllowedDateRange(): bool
    {
        if ($this->getInt('endTime') < $this->now) {
            return false;
        }

        if ($this->getInt('startTime') > strtotime('+1 week', $this->getInt('bookingRepeatStopWeekTstamp'))) {
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
        if (isset($this->data['lastPermittedWeekTstamp'], $this->data['beginWeekTimestampSelectedWeek']) && $this->data['beginWeekTimestampSelectedWeek'] > $this->data['lastPermittedWeekTstamp']) {
            return true;
        }

        // The resource can only be booked up to and including the "bookable until" date.
        $bookableUntil = ResourceBookingRestriction::getBookableUntil($this->getResource());

        return null !== $bookableUntil && $this->getInt('endTime') > $bookableUntil + self::SECONDS_IN_DAY;
    }

    /**
     * Returns false if the time slot is not available on the weekday of this slot
     * (tl_resource_booking_time_slot.weekdays).
     */
    public function isTimeSlotAvailable(): bool
    {
        return ResourceBookingRestriction::isTimeSlotAvailableOnWeekday(['weekdays' => $this->data['timeSlotWeekdays'] ?? null], (string) ($this->data['weekday'] ?? ''));
    }

    /**
     * Returns true if the slot belongs to a published time slot of the published timetable
     * of the resource and its start and end time match the time slot.
     */
    public function isValidTimeSlot(): bool
    {
        return true === ($this->data['isValidTimeSlot'] ?? false);
    }

    /**
     * Returns true if the number of requested items is between 1 and the number
     * of available items of the resource.
     */
    public function hasValidItemCount(): bool
    {
        return BookingRequestValidator::isValidItemCount($this->getInt('itemsBooked'), $this->getItemsAvailable());
    }

    /**
     * Returns true if a blocked period (e.g. holidays) covers this slot.
     */
    public function isInBlockedPeriod(): bool
    {
        return !empty($this->data['blockedPeriod']);
    }

    /**
     * Enough items are left for the requested items (own bookings are not counted).
     */
    public function canFulfillRequestedItems(): bool
    {
        return $this->countBookedItems(excludeOwn: true) + $this->getInt('itemsBooked') <= $this->getItemsAvailable();
    }

    /**
     * @return list<array<string, mixed>> The rows of tl_resource_booking
     */
    public function getBookings(): array
    {
        return $this->data['bookings'];
    }

    public function hasBookings(): bool
    {
        return !empty($this->data['bookings']);
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
        if (null === $this->loggedInMemberId || !$this->hasBookings() || !$this->isWithinAllowedDateRange()) {
            return false;
        }

        return null !== $this->getUserBooking();
    }

    /**
     * The booking of the logged-in member (complete row of tl_resource_booking), or null.
     *
     * @return array<string, mixed>|null
     */
    public function getUserBooking(): array|null
    {
        foreach ($this->data['bookings'] as $booking) {
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

        foreach ($this->data['bookings'] as $booking) {
            if ($excludeOwn && $this->isOwnedByLoggedInMember($booking)) {
                continue;
            }

            $total += (int) ($booking['itemsBooked'] ?? 1);
        }

        return $total;
    }

    private function getRemainingItems(): int
    {
        return $this->getItemsAvailable() - $this->countBookedItems();
    }

    /**
     * @return array<string, mixed>
     */
    private function getResource(): array
    {
        return $this->data['resource'] ?? [];
    }

    private function getItemsAvailable(): int
    {
        return (int) ($this->getResource()['itemsAvailable'] ?? 0);
    }

    private function getInt(string $key): int
    {
        return (int) ($this->data[$key] ?? 0);
    }
}
