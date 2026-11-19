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

use Markocupic\ResourceBookingBundle\Api\Input\CalendarQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class CalendarQueryTest extends TestCase
{
    public function testGetRequestsUseTheQueryString(): void
    {
        $query = CalendarQuery::fromRequest(Request::create('/_rbb/api/calendar', 'GET', ['resType' => '2', 'res' => '3', 'date' => '2026-11-20', 'initial' => '1']));

        $this->assertSame(['resType' => '2', 'res' => '3', 'date' => '2026-11-20'], $query->toSelection());
        $this->assertTrue($query->initial);
    }

    public function testPostRequestsUseTheBody(): void
    {
        $request = Request::create('/_rbb/api/bookings?res=9', 'POST', ['resType' => '2', 'res' => '3']);
        $query = CalendarQuery::fromRequest($request);

        $this->assertSame(['resType' => '2', 'res' => '3', 'date' => null], $query->toSelection());
        $this->assertFalse($query->initial);
    }

    public function testNonScalarValuesAreIgnored(): void
    {
        $query = CalendarQuery::fromRequest(Request::create('/_rbb/api/calendar', 'GET', ['res' => ['3'], 'initial' => ['1']]));

        $this->assertNull($query->resourceId);
        $this->assertFalse($query->initial);
    }
}
