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

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Markocupic\ResourceBookingBundle\Util\DateHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateHelperTest extends TestCase
{
    /**
     * 2026-11-11 is a wednesday.
     */
    #[DataProvider('provideFirstDaysOfWeek')]
    public function testReturnsTheFirstDayOfTheWeek(string $beginWeek, string $date, string $expected): void
    {
        $this->assertSame(strtotime($expected), DateHelper::getFirstDayOfWeek($beginWeek, strtotime($date.' 14:30')));
    }

    public static function provideFirstDaysOfWeek(): iterable
    {
        yield 'monday, wednesday' => ['monday', '2026-11-11', '2026-11-09'];
        yield 'monday, monday' => ['monday', '2026-11-09', '2026-11-09'];
        yield 'monday, sunday' => ['monday', '2026-11-15', '2026-11-09'];
        // The week starts on the sunday before, not on the sunday after (strtotime("sunday this week")).
        yield 'sunday, wednesday' => ['sunday', '2026-11-11', '2026-11-08'];
        yield 'sunday, sunday' => ['sunday', '2026-11-08', '2026-11-08'];
        yield 'sunday, saturday' => ['sunday', '2026-11-14', '2026-11-08'];
        yield 'saturday, friday' => ['saturday', '2026-11-13', '2026-11-07'];
    }

    public function testAnInvalidFirstDayOfTheWeekFallsBackToMonday(): void
    {
        $this->assertSame(strtotime('2026-11-09'), DateHelper::getFirstDayOfWeek('', strtotime('2026-11-11 08:00')));
        $this->assertSame(strtotime('2026-11-09'), DateHelper::getFirstDayOfWeek('someday', strtotime('2026-11-11 08:00')));
    }

    public function testAddsDaysAndWeeks(): void
    {
        $start = strtotime('2026-11-11 08:00');

        $this->assertSame(strtotime('2026-11-18 08:00'), DateHelper::addDays(7, $start));
        $this->assertSame(strtotime('2026-11-05 08:00'), DateHelper::addDays(-6, $start));
        $this->assertSame(strtotime('2026-11-25 08:00'), DateHelper::addWeeks(2, $start));
        $this->assertSame(strtotime('2026-10-28 08:00'), DateHelper::addWeeks(-2, $start));
        $this->assertSame($start, DateHelper::addWeeks(0, $start));
    }

    public function testKeepsTheTimeOfDayAcrossADaylightSavingTimeChange(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Zurich');

        try {
            // The clocks change on 2026-10-25
            $this->assertSame(strtotime('2026-10-27 08:00'), DateHelper::addDays(7, strtotime('2026-10-20 08:00')));
            $this->assertSame(strtotime('2026-10-27 08:00'), DateHelper::addWeeks(1, strtotime('2026-10-20 08:00')));
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function testValidatesTheBookingTime(): void
    {
        $this->assertTrue(DateHelper::isValidBookingTime('08:30'));
        $this->assertTrue(DateHelper::isValidBookingTime('00:00'));
        $this->assertTrue(DateHelper::isValidBookingTime('24:00'));
        $this->assertFalse(DateHelper::isValidBookingTime('8:30'));
        $this->assertFalse(DateHelper::isValidBookingTime('24:30'));
        $this->assertFalse(DateHelper::isValidBookingTime('abc'));
    }
}
