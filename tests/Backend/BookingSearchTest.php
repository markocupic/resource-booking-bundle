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

namespace Markocupic\ResourceBookingBundle\Tests\Backend;

use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Backend\BookingSearch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookingSearchTest extends TestCase
{
    #[DataProvider('termProvider')]
    public function testNormalize(string $term, string|null $expected): void
    {
        $this->assertSame($expected, BookingSearch::normalize($term));
    }

    public static function termProvider(): iterable
    {
        yield 'complete uuid' => ['0f8f2c4e-1b2c-4d5e-8f90-1234567890ab', '0f8f2c4e-1b2c-4d5e-8f90-1234567890ab'];
        yield 'first block' => ['0f8f2c4e', '0f8f2c4e'];
        yield 'trimmed and lowercase' => ['  0F8F2C4E-1B2C ', '0f8f2c4e-1b2c'];
        yield 'too short' => ['0f8f2c4', null];
        yield 'empty' => ['', null];
        yield 'LIKE wildcard' => ['0f8f2c4e%', null];
        yield 'underscore' => ['0f8f2c4e_', null];
        yield 'too long' => [str_repeat('a', 65), null];
    }

    public function testInvalidTermsAreNotSearched(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method($this->anything())
        ;

        $this->assertSame(['total' => 0, 'bookings' => []], (new BookingSearch($connection))->search('abc'));
    }

    public function testSearchesBookingsWhoseUuidStartsWithTheTerm(): void
    {
        $bookings = [['id' => 12, 'bookingUuid' => '0f8f2c4e-1b2c']];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('bookingUuid LIKE ?'), ['0f8f2c4e%'])
            ->willReturn('1')
        ;

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('LIMIT 50'), ['0f8f2c4e%'])
            ->willReturn($bookings)
        ;

        $this->assertSame(['total' => 1, 'bookings' => $bookings], (new BookingSearch($connection))->search(' 0F8F2C4E '));
    }

    public function testDoesNotLoadTheBookingsIfNothingIsFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn('0')
        ;

        $connection
            ->expects($this->never())
            ->method('fetchAllAssociative')
        ;

        $this->assertSame(['total' => 0, 'bookings' => []], (new BookingSearch($connection))->search('0f8f2c4e'));
    }
}
