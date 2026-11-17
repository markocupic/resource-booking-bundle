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

namespace Markocupic\ResourceBookingBundle\Tests\Validator;

use Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookingRequestValidatorTest extends TestCase
{
    private const PERMITTED = ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp'];

    public function testAcceptsAValidBookingRequest(): void
    {
        $this->expectNotToPerformAssertions();

        (new BookingRequestValidator())->validate($this->validPost(), self::PERMITTED);
    }

    public static function protectedFieldProvider(): iterable
    {
        foreach (BookingRequestValidator::PROTECTED_BOOKING_FIELDS as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('protectedFieldProvider')]
    public function testRejectsProtectedFieldsEvenIfTheyArePermitted(string $field): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        (new BookingRequestValidator())->validate([...$this->validPost(), $field => '1'], [...self::PERMITTED, $field]);
    }

    public function testRejectsConfirmed(): void
    {
        try {
            (new BookingRequestValidator())->validate([...$this->validPost(), 'confirmed' => '1'], self::PERMITTED);
            $this->fail('confirmed must not be accepted from the front end.');
        } catch (InvalidBookingRequestException $e) {
            $this->assertSame('confirmed', $e->getField());
            $this->assertSame(InvalidBookingRequestException::FIELD_NOT_PERMITTED, $e->getTranslationKey());
        }
    }

    public function testRejectsFieldsThatAreNotPermitted(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        (new BookingRequestValidator())->validate([...$this->validPost(), 'foo' => 'bar'], self::PERMITTED);
    }

    public function testRejectsAnInvalidInteger(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        (new BookingRequestValidator())->validate([...$this->validPost(), 'resourceId' => '3abc'], self::PERMITTED);
    }

    public function testRejectsANonArrayDateSelection(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        (new BookingRequestValidator())->validate([...$this->validPost(), 'bookingDateSelection' => '1-2-3-4'], self::PERMITTED);
    }

    public static function itemCountProvider(): iterable
    {
        yield 'one' => ['1', 5, 1, true];
        yield 'all' => ['5', 5, 5, true];
        yield 'empty means one' => ['', 5, 1, true];
        yield 'missing means one' => [null, 5, 1, true];
        yield 'zero' => ['0', 5, 0, false];
        yield 'negative' => ['-5', 5, 0, false];
        yield 'too many' => ['6', 5, 6, false];
        yield 'decimal' => ['2.5', 5, 0, false];
        yield 'text' => ['abc', 5, 0, false];
        yield 'array' => [['1'], 5, 0, false];
    }

    #[DataProvider('itemCountProvider')]
    public function testItemCount(mixed $submitted, int $itemsAvailable, int $parsed, bool $valid): void
    {
        $this->assertSame($parsed, BookingRequestValidator::parseItemCount($submitted));
        $this->assertSame($valid, BookingRequestValidator::isValidItemCount(BookingRequestValidator::parseItemCount($submitted), $itemsAvailable));
    }

    /**
     * @return array<string, mixed>
     */
    public function testParsesTheSlotSelection(): void
    {
        $this->assertSame(
            [
                ['timeSlotId' => 12, 'startTime' => 1795420800, 'endTime' => 1795423500],
                ['timeSlotId' => 3, 'startTime' => 1795507200, 'endTime' => 1795510800],
            ],
            BookingRequestValidator::parseSlotSelection(['12-1795420800-1795423500-1795046400', '3-1795507200-1795510800-1795046400']),
        );

        $this->assertSame([], BookingRequestValidator::parseSlotSelection(null));
        $this->assertSame([], BookingRequestValidator::parseSlotSelection([]));
    }

    public static function invalidSlotSelectionProvider(): iterable
    {
        yield 'not an array' => ['12-1795420800-1795423500-1795046400'];
        yield 'missing parts' => [['12-1795420800']];
        yield 'no separators' => [['121795420800']];
        yield 'letters' => [['12-abc-1795423500-1795046400']];
        yield 'negative value' => [['12--1795420800-1795423500-1795046400']];
        yield 'nested array' => [[['12-1795420800-1795423500-1795046400']]];
        yield 'time slot id 0' => [['0-1795420800-1795423500-1795046400']];
        yield 'end before start' => [['12-1795423500-1795420800-1795046400']];
        yield 'too many slots' => [array_fill(0, BookingRequestValidator::MAX_SELECTED_SLOTS + 1, '12-1795420800-1795423500-1795046400')];
    }

    #[DataProvider('invalidSlotSelectionProvider')]
    public function testRejectsAnInvalidSlotSelection(mixed $selection): void
    {
        try {
            BookingRequestValidator::parseSlotSelection($selection);
            $this->fail('An invalid slot selection must be rejected.');
        } catch (InvalidBookingRequestException $e) {
            $this->assertSame('bookingDateSelection', $e->getField());
            $this->assertSame(InvalidBookingRequestException::INVALID_VALUE, $e->getTranslationKey());
        }
    }

    public function testTheStateOfTheWeekCalendarIsAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        // Sent with every request (empty if nothing is selected)
        (new BookingRequestValidator())->validate([...$this->validPost(), 'resType' => '', 'res' => '', 'date' => ''], self::PERMITTED);
    }

    public function testTheStateOfTheWeekCalendarMustBeAString(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        (new BookingRequestValidator())->validate([...$this->validPost(), 'res' => ['3']], self::PERMITTED);
    }

    public function testValidateRejectsAnInvalidSlotSelection(): void
    {
        $this->expectException(InvalidBookingRequestException::class);

        (new BookingRequestValidator())->validate([...$this->validPost(), 'bookingDateSelection' => ['12-1795420800']], self::PERMITTED);
    }

    private function validPost(): array
    {
        return [
            'REQUEST_TOKEN' => 'abc',
            'action' => 'bookingRequest',
            'moduleKey' => '33_0',
            'resType' => '2',
            'res' => '3',
            'date' => '1795046400',
            'resourceId' => '3',
            'bookingRepeatStopWeekTstamp' => '1795046400',
            'bookingDateSelection' => ['12-1795420800-1795423500-1795046400'],
            'bookingDescription' => 'Werken 3b',
            'itemsBooked' => '1',
        ];
    }
}
