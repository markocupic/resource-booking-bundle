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

namespace Markocupic\ResourceBookingBundle\Tests\Functional\Cron;

use Markocupic\ResourceBookingBundle\Cron\MarkBookingsAsExpiredCron;
use Markocupic\ResourceBookingBundle\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Clock\MockClock;

class MarkBookingsAsExpiredCronTest extends FunctionalTestCase
{
    public function testMarksOnlyEndedBookingsAsExpired(): void
    {
        $now = strtotime('2030-10-16 12:00');

        $ended = $this->insertBooking(strtotime('2030-10-16 10:00'), strtotime('2030-10-16 11:00'));
        $running = $this->insertBooking(strtotime('2030-10-16 11:30'), strtotime('2030-10-16 12:30'));
        $future = $this->insertBooking(strtotime('2030-10-17 10:00'), strtotime('2030-10-17 11:00'));

        (new MarkBookingsAsExpiredCron($this->connection, new MockClock('@'.$now)))();

        $this->assertSame([$ended], $this->fetchIds('SELECT id FROM tl_resource_booking WHERE upcoming = 0 ORDER BY id'));
        $this->assertSame([$running, $future], $this->fetchIds('SELECT id FROM tl_resource_booking WHERE upcoming = 1 ORDER BY id'));
    }

    private function insertBooking(int $startTime, int $endTime): int
    {
        return $this->insertRow('tl_resource_booking', ['pid' => 1, 'startTime' => $startTime, 'endTime' => $endTime, 'upcoming' => 1]);
    }
}
