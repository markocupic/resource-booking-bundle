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
use Markocupic\ResourceBookingBundle\AjaxController\CancelBookingFormController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Symfony\Component\HttpFoundation\Request;

/**
 * The booking window is rendered on the server (data.bookingWindowHtml): opening, preview,
 * booking and cancelling.
 */
class BookingWindowTest extends IntegrationTestCase
{
    private int $page;

    private int $module;

    private int $resourceType;

    private int $resource;

    private int $timeSlot;

    /**
     * Next monday 08:00.
     */
    private int $startTime;

    protected function setUp(): void
    {
        parent::setUp();

        [$timetable, [$this->timeSlot]] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        [$this->resourceType, $this->resource] = $this->createResource($timetable);
        $this->module = $this->createModule([$this->resourceType]);
        $this->page = $this->createPage();
        $this->startTime = strtotime('monday next week') + 8 * 3600;
    }

    public function testTheBookingWindowIsRenderedWithTheSelectedSlot(): void
    {
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->sendApiRequest($this->createBookingRequest(['bookingDescription' => 'Probe "Chor"']), BookingFormValidationController::ACTION, $this->module, $this->page);
        $html = $response['data']['bookingWindowHtml'];

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertStringContainsString('id="rbbBookingWindowTitle_test"', $html);
        $this->assertStringContainsString('data-rbb-action="submit-booking"', $html);
        $this->assertMatchesRegularExpression('/name="slots\[\]"[^>]*value="'.preg_quote($this->currentSlotValue(), '/').'" checked/', $html);

        // The values sent are filled in again (escaped)
        $this->assertStringContainsString('value="Probe &quot;Chor&quot;"', $html);
        $this->assertStringNotContainsString('v-if', $html);
    }

    public function testAfterTheBookingOnlyTheResultIsDisplayed(): void
    {
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->sendApiRequest($this->createBookingRequest(), BookingController::ACTION, $this->module, $this->page);
        $html = $response['data']['bookingWindowHtml'];

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('data-rbb-action="submit-booking"', $html);
        $this->assertStringContainsString('rbb-alert--success', $html);
    }

    public function testTheCancelWindowIsOpenedForTheOwnBooking(): void
    {
        $alice = $this->createMember('alice');
        $booking = $this->insertBookingRow($this->resource, $alice, $this->timeSlot, $this->startTime, $this->startTime + 3600, ['bookingUuid' => 'abc-123']);
        $this->logIn('alice');

        $response = $this->openCancelWindow($booking);
        $html = $response['data']['bookingWindowHtml'];

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertStringContainsString('data-rbb-action="cancel-booking"', $html);
        $this->assertStringContainsString($booking.' / abc-123', $html);
    }

    public function testTheCancelWindowIsRefusedForTheBookingOfAnotherMember(): void
    {
        $bob = $this->createMember('bob');
        $booking = $this->insertBookingRow($this->resource, $bob, $this->timeSlot, $this->startTime, $this->startTime + 3600, ['bookingUuid' => 'bob-uuid']);
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->openCancelWindow($booking);
        $html = $response['data']['bookingWindowHtml'];

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertStringNotContainsString('data-rbb-action="cancel-booking"', $html);
        $this->assertStringNotContainsString('bob-uuid', $html);
    }

    public function testAfterCancellingOnlyTheResultIsDisplayed(): void
    {
        $alice = $this->createMember('alice');
        $booking = $this->insertBookingRow($this->resource, $alice, $this->timeSlot, $this->startTime, $this->startTime + 3600, ['bookingUuid' => 'abc-123']);
        $this->logIn('alice');

        $request = Request::create('https://example.com/_rbb/api/bookings/'.$booking.'?bookingUuid=abc-123', 'DELETE');
        $request->attributes->set('id', (string) $booking);

        $response = $this->sendApiRequest($request, CancelController::ACTION, $this->module, $this->page);
        $html = $response['data']['bookingWindowHtml'];

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame([], $this->fetchBookings());
        $this->assertStringNotContainsString('data-rbb-action="cancel-booking"', $html);
        $this->assertStringContainsString($booking.' / abc-123', $html);
        $this->assertStringContainsString('rbb-alert--success', $html);
    }

    private function currentSlotValue(): string
    {
        return self::slotValue($this->timeSlot, $this->startTime, $this->startTime + 3600);
    }

    /**
     * @param array<string, string> $fields
     */
    private function createBookingRequest(array $fields = []): Request
    {
        $weekStart = strtotime('monday this week', $this->startTime);

        return Request::create(
            'https://example.com/_rbb/api/bookings',
            'POST',
            [
                    'resType' => (string) $this->resourceType,
                    'res' => (string) $this->resource,
                    'date' => date('Y-m-d', $weekStart),
                    'resourceId' => (string) $this->resource,
                    'slots' => [$this->currentSlotValue()],
                    'requestedItems' => '1',
                    'repeatUntil' => (string) $weekStart,
                    ...$fields,
                ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function openCancelWindow(int $booking): array
    {
        $request = Request::create(
            'https://example.com/_rbb/api/actions/cancelBookingFormRequest',
            'POST',
            [
                    'resType' => (string) $this->resourceType,
                    'res' => (string) $this->resource,
                    'date' => date('Y-m-d', strtotime('monday this week', $this->startTime)),
                    'bookingId' => (string) $booking,
                ],
        );

        return $this->sendApiRequest($request, CancelBookingFormController::ACTION, $this->module, $this->page);
    }
}
