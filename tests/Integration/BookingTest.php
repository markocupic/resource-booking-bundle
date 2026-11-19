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
use Symfony\Component\HttpFoundation\Request;

/**
 * Booking requests (POST /_rbb/api/bookings) with real Contao models and a real database.
 */
class BookingTest extends IntegrationTestCase
{
    private int $page;

    private int $module;

    private int $resourceType;

    private int $resource;

    private int $timeSlot;

    /**
     * First day of the next week (monday).
     */
    private int $weekStart;

    protected function setUp(): void
    {
        parent::setUp();

        [$timetable, [$this->timeSlot]] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        [$this->resourceType, $this->resource] = $this->createResource($timetable);
        $this->module = $this->createModule([$this->resourceType]);
        $this->page = $this->createPage();
        $this->weekStart = strtotime('monday next week');
    }

    public function testBooksAFreeSlot(): void
    {
        $member = $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->book();

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertTrue($response['data']['isBooked']);

        $bookings = $this->fetchBookings();

        $this->assertCount(1, $bookings);
        $this->assertSame($this->resource, (int) $bookings[0]['pid']);
        $this->assertSame($member, (int) $bookings[0]['member']);
        $this->assertSame($this->timeSlot, (int) $bookings[0]['timeSlotId']);
        $this->assertSame($this->slotStart(), (int) $bookings[0]['startTime']);
        $this->assertSame($this->slotStart() + 3600, (int) $bookings[0]['endTime']);
        $this->assertSame(1, (int) $bookings[0]['itemsBooked']);
        $this->assertSame($this->module, (int) $bookings[0]['moduleId']);
        $this->assertNotEmpty($bookings[0]['bookingUuid']);
        // Resources confirm new bookings automatically by default
        $this->assertSame(1, (int) $bookings[0]['confirmed']);

        $this->assertSame([(int) $bookings[0]['id']], array_map('intval', array_column($response['data']['bookings'], 'id')));
    }

    public function testRepetitionsCreateOneBookingPerWeekWithTheSameUuid(): void
    {
        $this->createMember('alice');
        $this->logIn('alice');

        // The slot of next week and of the two following weeks
        $response = $this->book(repeatUntil: strtotime('+2 weeks', $this->weekStart));

        $this->assertSame('success', $response['status'], json_encode($response));

        $bookings = $this->fetchBookings();

        $this->assertCount(3, $bookings);
        $this->assertSame(
            [$this->slotStart(), strtotime('+1 week', $this->slotStart()), strtotime('+2 weeks', $this->slotStart())],
            array_map('intval', array_column($bookings, 'startTime')),
        );
        $this->assertCount(1, array_unique(array_column($bookings, 'bookingUuid')));
    }

    public function testBookedOutSlotCannotBeBookedByAnotherMember(): void
    {
        $bob = $this->createMember('bob');
        $this->createMember('alice');
        $this->insertBooking($bob);

        $this->logIn('alice');
        $response = $this->book();

        $this->assertSame('warning', $response['status'], json_encode($response));
        $this->assertFalse($response['data']['isBooked']);
        $this->assertSame([$bob], array_map('intval', array_column($this->fetchBookings(), 'member')));
    }

    public function testSlotInABlockedPeriodCannotBeBooked(): void
    {
        $this->createMember('alice');
        $this->insertRow('tl_resource_booking_blocked_period', [
            'title' => 'Ferien',
            'published' => 1,
            'scope' => 'all',
            'dateFrom' => $this->weekStart,
            'dateTo' => strtotime('+4 days', $this->weekStart),
            'tstamp' => time(),
        ]);

        $this->logIn('alice');
        $response = $this->book();

        $this->assertSame('warning', $response['status'], json_encode($response));
        $this->assertSame([], $this->fetchBookings());
    }

    public function testGuestsCannotBook(): void
    {
        $response = $this->book();

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertSame([], $this->fetchBookings());
    }

    /**
     * @return array<string, mixed>
     */
    private function book(int|null $repeatUntil = null): array
    {
        $request = Request::create(
            'https://example.com/_rbb/api/bookings',
            'POST',
            [
                'resType' => (string) $this->resourceType,
                'res' => (string) $this->resource,
                'date' => date('Y-m-d', $this->weekStart),
                'resourceId' => (string) $this->resource,
                'slots' => [\sprintf('%d-%d-%d-%d', $this->timeSlot, $this->slotStart(), $this->slotStart() + 3600, $this->weekStart)],
                'requestedItems' => '1',
                'repeatUntil' => (string) ($repeatUntil ?? $this->weekStart),
            ],
        );

        return $this->sendApiRequest($request, BookingController::ACTION, $this->module, $this->page);
    }

    private function insertBooking(int $member): void
    {
        $this->insertRow('tl_resource_booking', [
            'pid' => $this->resource,
            'member' => $member,
            'timeSlotId' => $this->timeSlot,
            'startTime' => $this->slotStart(),
            'endTime' => $this->slotStart() + 3600,
            'itemsBooked' => 1,
            'bookingUuid' => 'existing',
            'title' => 'Buchung',
            'tstamp' => time(),
        ]);
    }

    /**
     * Next monday 08:00.
     */
    private function slotStart(): int
    {
        return $this->weekStart + 8 * 3600;
    }
}
