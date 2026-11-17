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

namespace Markocupic\ResourceBookingBundle\Tests\Slot;

use Markocupic\ResourceBookingBundle\Slot\Slot;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the booking rules of the slot. The slot is a value object: bookings,
 * resource and the current time are passed to the constructor, no database access.
 */
class SlotTest extends TestCase
{
    public function testIsFullyBookedReflectsBookedItemsAgainstCapacity(): void
    {
        // 2 of 3 items booked -> not fully booked.
        $slot = $this->slotWith(3, [['itemsBooked' => 1], ['itemsBooked' => 1]]);
        $this->assertFalse($slot->isFullyBooked());

        // 3 of 3 items booked -> fully booked.
        $slot = $this->slotWith(3, [['itemsBooked' => 2], ['itemsBooked' => 1]]);
        $this->assertTrue($slot->isFullyBooked());
    }

    public function testCanFulfillRequestedItemsChecksRemainingCapacity(): void
    {
        // 2 booked + 1 requested = 3, capacity 3 -> fits.
        $slot = $this->slotWith(3, [['itemsBooked' => 1], ['itemsBooked' => 1]], requestedItems: 1);
        $this->assertTrue($slot->canFulfillRequestedItems());

        // 2 booked + 1 requested = 3, capacity 2 -> does not fit.
        $slot = $this->slotWith(2, [['itemsBooked' => 1], ['itemsBooked' => 1]], requestedItems: 1);
        $this->assertFalse($slot->canFulfillRequestedItems());
    }

    public function testBookingsWithoutExplicitItemCountAreCountedAsOne(): void
    {
        // Two bookings without "itemsBooked" default to 1 each -> 2 booked of 2 -> fully booked.
        $slot = $this->slotWith(2, [[], []]);

        $this->assertTrue($slot->isFullyBooked());
    }

    public function testOwnBookingIsRecognizedWhenMemberIdIsStoredAsString(): void
    {
        // The logged-in user's own booking must not count against the capacity,
        // even if the "member" value comes back from the DB as a string.
        $slot = $this->slotWith(
            2,
            [
                ['itemsBooked' => 1, 'member' => '5'], // own booking (string id)
                ['itemsBooked' => 1, 'member' => 9],
            ],
            requestedItems: 1,
            loggedInMemberId: 5,
        );

        // Own booking excluded => 1 booked + 1 requested = 2 <= 2 => fits.
        $this->assertTrue($slot->canFulfillRequestedItems());
    }

    public function testIsTimeSlotAvailableRespectsTheWeekdaysOfTheTimeSlot(): void
    {
        $weekdays = serialize(['monday', 'tuesday']);

        $slot = $this->slotWith(1, [], weekday: 'monday', timeSlotWeekdays: $weekdays);
        $this->assertTrue($slot->isTimeSlotAvailable());
        $this->assertTrue($slot->isBookable());

        $slot = $this->slotWith(1, [], weekday: 'wednesday', timeSlotWeekdays: $weekdays);
        $this->assertFalse($slot->isTimeSlotAvailable());
        $this->assertFalse($slot->isBookable());

        // Time slots without weekdays (not migrated yet) are available on every weekday.
        $slot = $this->slotWith(1, [], weekday: 'sunday');
        $this->assertTrue($slot->isTimeSlotAvailable());
    }

    public function testInvalidTimeSlotIsNotBookable(): void
    {
        $slot = $this->slotWith(1, [], isValidTimeSlot: false);

        $this->assertFalse($slot->isValidTimeSlot());
        $this->assertFalse($slot->isBookable());
    }

    public function testSlotWithoutValidationResultIsNotBookable(): void
    {
        // A slot without the result of the time slot validation (see SlotFactory::create()) must never be bookable
        $slot = $this->slotWith(1, [], isValidTimeSlot: null);

        $this->assertFalse($slot->isValidTimeSlot());
        $this->assertFalse($slot->isBookable());
    }

    public function testItemCountMustBeBetweenOneAndTheAvailableItems(): void
    {
        $this->assertTrue($this->slotWith(3, [], requestedItems: 3)->isBookable());

        foreach ([0, -5, 4] as $requestedItems) {
            $slot = $this->slotWith(3, [], requestedItems: $requestedItems);

            $this->assertFalse($slot->hasValidItemCount(), (string) $requestedItems);
            $this->assertFalse($slot->isBookable(), (string) $requestedItems);
        }
    }

    public function testSlotInABlockedPeriodIsNotBookable(): void
    {
        $slot = $this->slotWith(1, []);
        $this->assertFalse($slot->isInBlockedPeriod());
        $this->assertTrue($slot->isBookable());

        $slot = $this->slotWith(1, [], blockedPeriod: ['id' => 1]);
        $this->assertTrue($slot->isInBlockedPeriod());
        $this->assertFalse($slot->isBookable());
    }

    public function testIsWithinAllowedDateRangeRespectsBookableUntil(): void
    {
        $bookableUntil = strtotime('today +10 days');
        $resource = [
            'itemsAvailable' => 1,
            'addBookableUntil' => '1',
            'bookableUntil' => (string) $bookableUntil,
        ];

        // Slot on the last bookable day -> allowed.
        $slot = $this->slotWith(1, [], resource: $resource, startTime: $bookableUntil + 8 * 3600, endTime: $bookableUntil + 9 * 3600);
        $this->assertTrue($slot->isWithinAllowedDateRange());

        // Slot on the day after -> not allowed.
        $slot = $this->slotWith(1, [], resource: $resource, startTime: $bookableUntil + 86400 + 8 * 3600, endTime: $bookableUntil + 86400 + 9 * 3600);
        $this->assertTrue($slot->isOutsideBookingPeriod());
        $this->assertFalse($slot->isWithinAllowedDateRange());
        $this->assertFalse($slot->isBookable());
    }

    public function testIsWithinAllowedDateRangeRespectsTheWeeksAheadLimit(): void
    {
        $startTime = strtotime('today +20 days 08:00');

        // The slot lies in the last permitted week -> allowed.
        $slot = $this->slotWith(1, [], startTime: $startTime, endTime: $startTime + 3600, firstDayOfWeek: 2000, lastPermittedWeek: 2000);
        $this->assertTrue($slot->isWithinAllowedDateRange());

        // The slot lies after the last permitted week -> not allowed.
        $slot = $this->slotWith(1, [], startTime: $startTime, endTime: $startTime + 3600, firstDayOfWeek: 3000, lastPermittedWeek: 2000);
        $this->assertTrue($slot->isOutsideBookingPeriod());
        $this->assertFalse($slot->isWithinAllowedDateRange());
        $this->assertFalse($slot->isBookable());
    }

    public function testSlotInThePastIsNotOutsideTheBookingPeriod(): void
    {
        // A slot in the past is invalid, but not "outside the booking period" (different error message).
        $startTime = strtotime('yesterday 08:00');
        $slot = $this->slotWith(1, [], startTime: $startTime, endTime: $startTime + 3600);

        $this->assertFalse($slot->isOutsideBookingPeriod());
        $this->assertFalse($slot->isWithinAllowedDateRange());
    }

    public function testRowContainsTheResultsOfTheRules(): void
    {
        $slot = $this->slotWith(2, [['itemsBooked' => 1, 'member' => 5]], loggedInMemberId: 5);
        $row = $slot->row();

        $this->assertTrue($row['isBookable']);
        $this->assertTrue($row['userIsLoggedIn']);
        $this->assertTrue($row['hasBookings']);
        $this->assertSame(1, $row['bookingCount']);
        $this->assertSame(1, $row['itemsStillAvailable']);
        $this->assertTrue($row['userHasBooked']);
        $this->assertSame(5, $row['userBooking']['member']);
        $this->assertTrue($row['isCancelable']);

        // Magic access: input data and results of the rules
        $this->assertSame(2, $slot->resource['itemsAvailable']);
        $this->assertTrue($slot->isCancelable);
    }

    public function testGuestCannotCancel(): void
    {
        $slot = $this->slotWith(2, [['itemsBooked' => 1, 'member' => 5]]);

        $this->assertFalse($slot->isCancelable());
        $this->assertNull($slot->getUserBooking());
        $this->assertFalse($slot->userIsLoggedIn);
    }

    public function testWithBookingDataReturnsACopy(): void
    {
        $slot = $this->slotWith(1, []);
        $copy = $slot->withBookingData(['title' => 'Booking']);

        $this->assertNotSame($slot, $copy);
        $this->assertSame([], $slot->dataBooking);
        $this->assertSame(['title' => 'Booking'], $copy->dataBooking);
        $this->assertSame($slot->startTime, $copy->startTime);
    }

    /**
     * @param array<int, array<string, mixed>> $bookings
     */
    private function slotWith(int $itemsAvailable, array $bookings, int $requestedItems = 1, array|null $resource = null, string $weekday = 'monday', int|null $startTime = null, int|null $endTime = null, int|null $firstDayOfWeek = null, int|null $lastPermittedWeek = null, string|null $timeSlotWeekdays = null, array|null $blockedPeriod = null, bool|null $isValidTimeSlot = true, int|null $loggedInMemberId = null): Slot
    {
        $startTime ??= strtotime('tomorrow 08:00');
        $endTime ??= $startTime + 3600;

        return new Slot(
            [
                'resource' => $resource ?? ['itemsAvailable' => $itemsAvailable],
                'itemsBooked' => $requestedItems,
                'bookings' => $bookings,
                'weekday' => $weekday,
                'startTime' => $startTime,
                'endTime' => $endTime,
                // Timestamp of the first day of the week up to which the booking is repeated.
                'bookingRepeatStopWeekTstamp' => $startTime,
                'beginWeekTimestampSelectedWeek' => $firstDayOfWeek,
                'lastPermittedWeekTstamp' => $lastPermittedWeek,
                'timeSlotWeekdays' => $timeSlotWeekdays,
                'blockedPeriod' => $blockedPeriod,
                'isValidTimeSlot' => $isValidTimeSlot,
            ],
            $loggedInMemberId, time(),
        );
    }
}
