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

namespace Markocupic\ResourceBookingBundle\Tests\Integration;

use Markocupic\ResourceBookingBundle\AjaxController\BookingController;
use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Booking rules of resources, time slots and blocked periods, checked with the booking
 * preview (POST /_rbb/api/bookings/validate) and with booking requests.
 */
class BookingRulesTest extends IntegrationTestCase
{
    private int $page;

    private int $timetable;

    private int $timeSlot;

    /**
     * First day of the next week (monday).
     */
    private int $weekStart;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->timetable, [$this->timeSlot]] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        $this->page = $this->createPage();
        $this->weekStart = strtotime('monday next week');

        $this->createMember('alice');
        $this->logIn('alice');
    }

    public function testThePreviewConfirmsAFreeSlotWithoutBooking(): void
    {
        [$type, $resource] = $this->createResource($this->timetable);
        $module = $this->createModule([$type]);

        $response = $this->validate($module, $type, $resource, $this->monday());

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertTrue($response['data']['isBookable']);
        $this->assertCount(1, $response['fullData']['slots']);
        $this->assertNotEmpty($response['messages']['confirm']);
        $this->assertSame([], $this->fetchBookings());
    }

    public function testSeveralItemsCanBeBookedUpToTheAvailableItems(): void
    {
        [$type, $resource] = $this->createResource($this->timetable, 3);
        $module = $this->createModule([$type]);
        $bob = $this->createMember('bob');
        $this->insertBookingRow($resource, $bob, $this->timeSlot, $this->monday(), $this->monday() + 3600);

        // 1 of 3 items booked: 2 items are left
        $response = $this->book($module, $type, $resource, $this->monday(), requestedItems: 2);

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame([1, 2], array_map('intval', array_column($this->fetchBookings(), 'itemsBooked')));

        // Fully booked now
        $this->createMember('carol');
        $this->logIn('carol');
        $response = $this->book($module, $type, $resource, $this->monday());

        $this->assertSame('warning', $response['status'], json_encode($response));
        $this->assertCount(2, $this->fetchBookings());
    }

    public function testNotMoreItemsThanAvailableCanBeRequested(): void
    {
        [$type, $resource] = $this->createResource($this->timetable, 2);
        $module = $this->createModule([$type]);

        $response = $this->validate($module, $type, $resource, $this->monday(), requestedItems: 3);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['data']['isBookable']);
        $this->assertFalse($response['fullData']['slots'][0]['hasValidItemCount']);

        $response = $this->book($module, $type, $resource, $this->monday(), requestedItems: 3);

        $this->assertNotSame('success', $response['status'], json_encode($response));
        $this->assertSame([], $this->fetchBookings());
    }

    public function testATimeSlotCanOnlyBeBookedOnItsWeekdays(): void
    {
        [$type, $resource] = $this->createResource($this->timetable);
        $module = $this->createModule([$type]);
        $this->connection->update('tl_resource_booking_time_slot', ['weekdays' => 'tuesday,wednesday'], ['id' => $this->timeSlot]);

        $response = $this->validate($module, $type, $resource, $this->monday());

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['fullData']['slots'][0]['isAvailableOnWeekday']);

        // Tuesday
        $tuesday = strtotime('+1 day', $this->weekStart) + 8 * 3600;
        $response = $this->validate($module, $type, $resource, $tuesday);

        $this->assertSame('success', $response['status'], json_encode($response));

        $this->assertNotSame('success', $this->book($module, $type, $resource, $this->monday())['status']);
        $this->assertSame([], $this->fetchBookings());
    }

    public function testAResourceCanOnlyBeBookedTheConfiguredWeeksAhead(): void
    {
        [$type, $resource] = $this->createResource($this->timetable, 1, ['weeksAhead' => 1]);
        $module = $this->createModule([$type]);

        // Next week: allowed
        $this->assertSame('success', $this->validate($module, $type, $resource, $this->monday())['status']);

        // In three weeks: outside the booking period
        $inThreeWeeks = strtotime('+2 weeks', $this->weekStart) + 8 * 3600;
        $response = $this->validate($module, $type, $resource, $inThreeWeeks);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['data']['isBookable']);

        $this->assertNotSame('success', $this->book($module, $type, $resource, $inThreeWeeks)['status']);
        $this->assertSame([], $this->fetchBookings());
    }

    public function testAResourceCanOnlyBeBookedUntilTheBookableUntilDate(): void
    {
        // Bookable up to and including next monday
        [$type, $resource] = $this->createResource($this->timetable, 1, ['addBookableUntil' => 1, 'bookableUntil' => $this->weekStart]);
        $module = $this->createModule([$type]);

        $this->assertSame('success', $this->validate($module, $type, $resource, $this->monday())['status']);

        $tuesday = strtotime('+1 day', $this->weekStart) + 8 * 3600;
        $response = $this->validate($module, $type, $resource, $tuesday);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['fullData']['slots'][0]['isInBookingWindow']);
    }

    public function testAPastSlotCannotBeBooked(): void
    {
        [$type, $resource] = $this->createResource($this->timetable);
        $module = $this->createModule([$type]);
        $lastWeek = strtotime('-2 weeks', $this->weekStart) + 8 * 3600;

        $response = $this->validate($module, $type, $resource, $lastWeek);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['fullData']['slots'][0]['isInBookingWindow']);

        $this->assertNotSame('success', $this->book($module, $type, $resource, $lastWeek)['status']);
        $this->assertSame([], $this->fetchBookings());
    }

    public function testABlockedPeriodOnlyBlocksItsResources(): void
    {
        [$type, $resource] = $this->createResource($this->timetable);
        [, $otherResource] = $this->createResource($this->timetable);
        $module = $this->createModule([$type]);

        $blockedPeriod = $this->insertRow('tl_resource_booking_blocked_period', [
            'title' => 'Renovation',
            'description' => 'Der Raum wird renoviert.',
            'published' => 1,
            'scope' => 'resources',
            'resources' => (string) $otherResource,
            'dateFrom' => $this->weekStart,
            'dateTo' => strtotime('+4 days', $this->weekStart),
            'tstamp' => time(),
        ]);

        // Another resource is blocked
        $this->assertSame('success', $this->validate($module, $type, $resource, $this->monday())['status']);

        // This resource is blocked
        $this->connection->update('tl_resource_booking_blocked_period', ['resources' => $resource.','.$otherResource], ['id' => $blockedPeriod]);
        $response = $this->validate($module, $type, $resource, $this->monday());

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertTrue($response['fullData']['slots'][0]['isInBlockedPeriod']);
        $this->assertSame('Der Raum wird renoviert.', $response['fullData']['slots'][0]['blockedPeriodDescription']);
    }

    public function testAnUnpublishedBlockedPeriodDoesNotBlock(): void
    {
        [$type, $resource] = $this->createResource($this->timetable);
        $module = $this->createModule([$type]);

        $this->insertRow('tl_resource_booking_blocked_period', [
            'title' => 'Ferien',
            'published' => 0,
            'scope' => 'all',
            'dateFrom' => $this->weekStart,
            'dateTo' => strtotime('+4 days', $this->weekStart),
            'tstamp' => time(),
        ]);

        $this->assertSame('success', $this->book($module, $type, $resource, $this->monday())['status']);
    }

    public function testAResourceOfAnotherModuleCannotBeBooked(): void
    {
        [$type] = $this->createResource($this->timetable);
        [, $otherResource] = $this->createResource($this->timetable);
        $module = $this->createModule([$type]);

        $response = $this->book($module, $type, $otherResource, $this->monday());

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertSame([], $this->fetchBookings());
    }

    public function testAnUnpublishedResourceCannotBeBooked(): void
    {
        [$type, $resource] = $this->createResource($this->timetable, 1, ['published' => 0]);
        $module = $this->createModule([$type]);

        $this->assertNotSame('success', $this->book($module, $type, $resource, $this->monday())['status']);
        $this->assertSame([], $this->fetchBookings());
    }

    public function testBookingsMustBeConfirmedIfTheResourceDoesNotConfirmThemAutomatically(): void
    {
        [$type, $resource] = $this->createResource($this->timetable, 1, ['autoConfirm' => 0]);
        $module = $this->createModule([$type]);

        $response = $this->book($module, $type, $resource, $this->monday());

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame([0], array_map('intval', array_column($this->fetchBookings(), 'confirmed')));
    }

    public function testTheOwnBookingCanBeExtendedWithoutCountingItTwice(): void
    {
        // The own booking is not counted when the member books the slot again (e.g. more items)
        [$type, $resource] = $this->createResource($this->timetable, 2);
        $module = $this->createModule([$type]);
        $alice = (int) $this->connection->fetchOne("SELECT id FROM tl_member WHERE username = 'alice'");
        $this->insertBookingRow($resource, $alice, $this->timeSlot, $this->monday(), $this->monday() + 3600);

        $response = $this->validate($module, $type, $resource, $this->monday(), requestedItems: 2);

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertTrue($response['fullData']['slots'][0]['canFulfillRequestedItems']);
    }

    /**
     * Next monday 08:00.
     */
    private function monday(): int
    {
        return $this->weekStart + 8 * 3600;
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(int $module, int $type, int $resource, int $startTime, int $requestedItems = 1): array
    {
        return $this->sendApiRequest($this->createRequest($type, $resource, $startTime, $requestedItems), BookingFormValidationController::ACTION, $module, $this->page);
    }

    /**
     * @return array<string, mixed>
     */
    private function book(int $module, int $type, int $resource, int $startTime, int $requestedItems = 1): array
    {
        return $this->sendApiRequest($this->createRequest($type, $resource, $startTime, $requestedItems), BookingController::ACTION, $module, $this->page);
    }

    private function createRequest(int $type, int $resource, int $startTime, int $requestedItems): Request
    {
        $weekStart = strtotime('monday this week', $startTime);

        return Request::create(
            'https://example.com/_rbb/api/bookings',
            'POST',
            [
                'resType' => (string) $type,
                'res' => (string) $resource,
                'date' => date('Y-m-d', $weekStart),
                'resourceId' => (string) $resource,
                'slots' => [self::slotValue($this->timeSlot, $startTime, $startTime + 3600)],
                'requestedItems' => (string) $requestedItems,
                'repeatUntil' => (string) $weekStart,
            ],
        );
    }
}
