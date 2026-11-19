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

use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cancellations (DELETE /_rbb/api/bookings/{id}) with real Contao models and a real database.
 */
class CancelBookingTest extends IntegrationTestCase
{
    private int $page;

    private int $module;

    private int $resource;

    private int $timeSlot;

    protected function setUp(): void
    {
        parent::setUp();

        [$timetable, [$this->timeSlot]] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        [$resourceType, $this->resource] = $this->createResource($timetable);
        $this->module = $this->createModule([$resourceType]);
        $this->page = $this->createPage();
    }

    public function testCancelsOwnBooking(): void
    {
        $alice = $this->createMember('alice');
        $booking = $this->insertBooking($alice, strtotime('monday next week 08:00'));

        $this->logIn('alice');
        $response = $this->cancel($booking);

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame([$booking], $response['data']['cancelledBookingIds']);
        $this->assertSame([], $this->fetchBookings());
    }

    public function testCannotCancelTheBookingOfAnotherMember(): void
    {
        $bob = $this->createMember('bob');
        $this->createMember('alice');
        $booking = $this->insertBooking($bob, strtotime('monday next week 08:00'));

        $this->logIn('alice');
        $response = $this->cancel($booking);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertCount(1, $this->fetchBookings());
    }

    public function testCannotCancelAPastBooking(): void
    {
        $alice = $this->createMember('alice');
        $booking = $this->insertBooking($alice, strtotime('monday last week 08:00'));

        $this->logIn('alice');
        $response = $this->cancel($booking);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertCount(1, $this->fetchBookings());
    }

    public function testCancelsTheRepetitionsOnlyOnRequest(): void
    {
        $alice = $this->createMember('alice');
        $first = strtotime('monday next week 08:00');
        $bookings = [];

        foreach ([0, 1, 2] as $week) {
            $bookings[] = $this->insertBooking($alice, strtotime(\sprintf('+%d weeks', $week), $first), 'series');
        }

        $this->logIn('alice');

        // Only the selected booking
        $response = $this->cancel($bookings[0]);

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame([$bookings[1], $bookings[2]], array_map('intval', array_column($this->fetchBookings(), 'id')));

        // The selected booking and its repetitions
        $response = $this->cancel($bookings[1], deleteRepetitions: true);

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame([], $this->fetchBookings());
    }

    public function testGuestsCannotCancel(): void
    {
        $alice = $this->createMember('alice');
        $booking = $this->insertBooking($alice, strtotime('monday next week 08:00'));

        $response = $this->cancel($booking);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertCount(1, $this->fetchBookings());
    }

    /**
     * @return array<string, mixed>
     */
    private function cancel(int $bookingId, bool $deleteRepetitions = false): array
    {
        $uri = 'https://example.com/_rbb/api/bookings/'.$bookingId.($deleteRepetitions ? '?deleteRepetitions=1' : '');
        $request = Request::create($uri, 'DELETE');
        $request->attributes->set('id', (string) $bookingId);

        return $this->sendApiRequest($request, CancelController::ACTION, $this->module, $this->page);
    }

    private function insertBooking(int $member, int $startTime, string $bookingUuid = ''): int
    {
        return $this->insertRow('tl_resource_booking', [
            'pid' => $this->resource,
            'member' => $member,
            'timeSlotId' => $this->timeSlot,
            'startTime' => $startTime,
            'endTime' => $startTime + 3600,
            'itemsBooked' => 1,
            'bookingUuid' => '' !== $bookingUuid ? $bookingUuid : uniqid('booking', true),
            'title' => 'Buchung',
            'tstamp' => time(),
        ]);
    }
}
