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

use Markocupic\ResourceBookingBundle\Api\Input\BookingInput;
use Markocupic\ResourceBookingBundle\Api\Input\SelectedSlot;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class BookingInputTest extends TestCase
{
    public function testCreatesTheInputFromTheRequest(): void
    {
        $input = BookingInput::fromRequest($this->createRequest(['phone' => '079 123 45 67']), ['phone']);

        $this->assertSame(3, $input->resourceId);
        $this->assertEquals([new SelectedSlot(12, 1795420800, 1795423500), new SelectedSlot(4, 1795507200, 1795510800)], $input->slots);
        $this->assertSame(2, $input->requestedItems);
        $this->assertSame(1795046400, $input->repeatUntil);
        $this->assertSame('Werken 3b', $input->bookingDescription);

        // Only the custom form fields, not the fields of the bundle or the selection of the calendar
        $this->assertSame(['phone' => '079 123 45 67'], $input->formFields);
    }

    public function testAnEmptyItemCountMeansOneItem(): void
    {
        $this->assertSame(1, BookingInput::fromRequest($this->createRequest(['requestedItems' => '']), [])->requestedItems);
    }

    /**
     * Not a positive integer: 0, the slot rejects it (invalidItemsBooked).
     */
    public function testAnInvalidItemCountBecomesZero(): void
    {
        $this->assertSame(0, BookingInput::fromRequest($this->createRequest(['requestedItems' => 'abc']), [])->requestedItems);
    }

    public function testResourceAndRepetitionsAreRequired(): void
    {
        foreach (['resourceId', 'repeatUntil'] as $field) {
            $post = $this->validPost();
            unset($post[$field]);

            try {
                BookingInput::fromRequest(Request::create('/_rbb/api/bookings', 'POST', $post), []);
                $this->fail(\sprintf('Missing %s must be rejected.', $field));
            } catch (InvalidBookingRequestException $e) {
                $this->assertSame($field, $e->getField());
            }
        }
    }

    public function testCustomFieldsMustBePermitted(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        BookingInput::fromRequest($this->createRequest(['phone' => '079']), []);
    }

    /**
     * The old field names (before version 6) are rejected: custom templates have to be adapted.
     */
    public function testTheOldFieldNamesAreRejected(): void
    {
        foreach (['itemsBooked' => '1', 'bookingRepeatStopWeekTstamp' => '1795046400', 'bookingDateSelection' => ['12-1795420800-1795423500-1795046400']] as $field => $value) {
            try {
                BookingInput::fromRequest($this->createRequest([$field => $value]), []);
                $this->fail(\sprintf('%s must be rejected.', $field));
            } catch (InvalidBookingRequestException $e) {
                $this->assertSame($field, $e->getField());
            }
        }
    }

    public function testProtectedFieldsAreRejected(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        BookingInput::fromRequest($this->createRequest(['confirmed' => '1']), ['confirmed']);
    }

    public function testInvalidSlotsAreRejected(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        BookingInput::fromRequest($this->createRequest(['slots' => ['12-1795420800']]), []);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function createRequest(array $post): Request
    {
        return Request::create('/_rbb/api/bookings', 'POST', [...$this->validPost(), ...$post]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPost(): array
    {
        return [
            'resType' => '2',
            'res' => '3',
            'date' => '1795046400',
            'resourceId' => '3',
            'slots' => ['12-1795420800-1795423500-1795046400', '4-1795507200-1795510800-1795046400'],
            'requestedItems' => '2',
            'repeatUntil' => '1795046400',
            'bookingDescription' => 'Werken 3b',
        ];
    }
}
