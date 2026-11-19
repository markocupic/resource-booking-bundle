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

namespace Markocupic\ResourceBookingBundle\Tests\Api\Input;

use Markocupic\ResourceBookingBundle\Api\Input\CancelBookingInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CancelBookingInputTest extends TestCase
{
    public function testReadsTheBookingFromTheRouteAndTheRepetitionsFromTheQueryString(): void
    {
        $input = CancelBookingInput::fromRequest($this->createRequest('15', ['deleteRepetitions' => '1']));

        $this->assertSame(15, $input->bookingId);
        $this->assertTrue($input->deleteRepetitions);
    }

    public function testRepetitionsAreKeptByDefault(): void
    {
        $this->assertFalse(CancelBookingInput::fromRequest($this->createRequest('15'))->deleteRepetitions);
        $this->assertFalse(CancelBookingInput::fromRequest($this->createRequest('15', ['deleteRepetitions' => ['1']]))->deleteRepetitions);
    }

    public function testAnInvalidIdBecomesZero(): void
    {
        $this->assertSame(0, CancelBookingInput::fromRequest($this->createRequest('abc'))->bookingId);
        $this->assertSame(0, CancelBookingInput::fromRequest($this->createRequest(null))->bookingId);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function createRequest(string|null $id, array $query = []): Request
    {
        // Request::create() puts the parameters of a DELETE request into the body: query string in the URI
        $request = Request::create('/_rbb/api/bookings/'.$id.($query ? '?'.http_build_query($query) : ''), 'DELETE');
        $request->attributes->set('id', $id);

        return $request;
    }
}
