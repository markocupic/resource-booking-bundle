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

    public function testRejectsIsBlocked(): void
    {
        try {
            (new BookingRequestValidator())->validate([...$this->validPost(), 'isBlocked' => '1'], self::PERMITTED);
            $this->fail('isBlocked must not be accepted from the front end.');
        } catch (InvalidBookingRequestException $e) {
            $this->assertSame('isBlocked', $e->getField());
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
    private function validPost(): array
    {
        return [
            'REQUEST_TOKEN' => 'abc',
            'action' => 'bookingRequest',
            'moduleKey' => '33_0',
            'resourceId' => '3',
            'bookingRepeatStopWeekTstamp' => '1795046400',
            'bookingDateSelection' => ['12-1795420800-1795423500-1795046400'],
            'bookingDescription' => 'Werken 3b',
            'itemsBooked' => '1',
        ];
    }
}
