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
    public function testGetFirstDayOfCurrentWeekReturnsTheBeginningOfTheWeek(string $beginWeek, string $date, string $expected): void
    {
        $timestamp = strtotime($date.' 14:30');

        $this->assertSame(strtotime($expected), DateHelper::getFirstDayOfCurrentWeek(['beginWeek' => $beginWeek], $timestamp));
        $this->assertSame(strtotime($expected), DateHelper::getFirstDayOfWeek(['beginWeek' => $beginWeek], $timestamp));
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

    public function testGetFirstDayOfWeekStartsOnMondayWithoutBeginWeek(): void
    {
        $this->assertSame(strtotime('2026-11-09'), DateHelper::getFirstDayOfWeek([], strtotime('2026-11-11 08:00')));
    }
}
