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

namespace Markocupic\ResourceBookingBundle\Restriction;

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Markocupic\ResourceBookingBundle\Util\TimeOfDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeOfDayTest extends TestCase
{
    public static function timeProvider(): iterable
    {
        yield 'hh:mm' => ['08:30', 30600];
        yield 'h:mm' => ['8:30', 30600];
        yield 'midnight' => ['00:00', 0];
        yield 'end of day' => ['24:00', 86400];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
        yield 'after the end of the day' => ['24:30', null];
        yield 'invalid' => ['8 Uhr', null];
    }

    #[DataProvider('timeProvider')]
    public function testConvertsTimesIntoSeconds(mixed $time, int|null $expected): void
    {
        $this->assertSame($expected, TimeOfDay::toSeconds($time));
    }

    public function testConvertsContaoTimeValues(): void
    {
        // Contao stores times as timestamp of 1970-01-01 in the local time zone
        $this->assertSame(30600, TimeOfDay::toSeconds(mktime(8, 30, 0, 1, 1, 1970)));
        $this->assertSame(30600, TimeOfDay::toSeconds((string) mktime(8, 30, 0, 1, 1, 1970)));
    }
}
