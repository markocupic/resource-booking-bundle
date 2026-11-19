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

use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Errors of the week calendar API are always JSON in the format of the Ajax responses.
 */
class ApiErrorTest extends IntegrationTestCase
{
    private int $page;

    private int $module;

    protected function setUp(): void
    {
        parent::setUp();

        [$timetable] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        [$resourceType] = $this->createResource($timetable);
        $this->module = $this->createModule([$resourceType]);
        $this->page = $this->createPage();
    }

    public function testAnUnknownActionReturnsAnError(): void
    {
        $response = $this->sendApiRequest(Request::create('https://example.com/_rbb/api/unknown'), 'unknownAction', $this->module, $this->page);

        $this->assertSame('error', $response['status']);
        $this->assertSame('unknownAction', $response['action']);
        $this->assertNotEmpty($response['messages']['error']);
        $this->assertSame([], $response['data']);
    }

    public function testGuestsGetNoBookingPreview(): void
    {
        $request = Request::create('https://example.com/_rbb/api/bookings/validate', 'POST', ['slots' => []]);
        $response = $this->sendApiRequest($request, BookingFormValidationController::ACTION, $this->module, $this->page);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['data']['isBookable']);
        $this->assertNotEmpty($response['messages']['error']);
    }

    public function testAnInvalidBookingRequestIsRejected(): void
    {
        $this->createMember('alice');
        $this->logIn('alice');

        // Manipulated slot value
        $request = Request::create(
            'https://example.com/_rbb/api/bookings/validate',
            'POST',
            [
                    'resourceId' => '1',
                    'slots' => ['1-abc'],
                    'requestedItems' => '1',
                ],
        );

        $response = $this->sendApiRequest($request, BookingFormValidationController::ACTION, $this->module, $this->page);

        $this->assertSame('error', $response['status'], json_encode($response));
        $this->assertFalse($response['data']['isBookable']);
    }
}
