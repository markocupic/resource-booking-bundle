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

use Markocupic\ResourceBookingBundle\Cron\PurgePastBookingsCron;
use Markocupic\ResourceBookingBundle\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Clock\MockClock;

class PurgePastBookingsCronTest extends FunctionalTestCase
{
    /**
     * Wednesday; the current week starts on monday 2030-10-14 or on sunday 2030-10-13.
     */
    private const NOW = '2030-10-16 12:00';

    public function testDeletesTheBookingsBeforeTheFirstWeekTheUserCanNavigateTo(): void
    {
        $resource = $this->insertResource(weeksBack: 2, beginWeek: 'monday');

        // Two weeks back from monday 2030-10-14: monday 2030-09-30
        $tooOld = $this->insertBooking($resource, '2030-09-29 10:00', '2030-09-29 11:00');
        $firstPermittedWeek = $this->insertBooking($resource, '2030-09-30 10:00', '2030-09-30 11:00');
        $currentWeek = $this->insertBooking($resource, '2030-10-15 10:00', '2030-10-15 11:00');

        $this->runCron();

        $this->assertSame([$firstPermittedWeek, $currentWeek], $this->fetchIds('SELECT id FROM tl_resource_booking ORDER BY id'));
        $this->assertNotContains($tooOld, $this->fetchIds('SELECT id FROM tl_resource_booking'));
    }

    public function testTheWeekStartsOnTheFirstDayOfTheTimetable(): void
    {
        $monday = $this->insertResource(weeksBack: 1, beginWeek: 'monday');
        $sunday = $this->insertResource(weeksBack: 1, beginWeek: 'sunday');

        // Limit for monday: 2030-10-07, for sunday: 2030-10-06
        $mondayBooking = $this->insertBooking($monday, '2030-10-06 10:00', '2030-10-06 11:00');
        $sundayBooking = $this->insertBooking($sunday, '2030-10-06 10:00', '2030-10-06 11:00');

        $this->runCron();

        $this->assertSame([$sundayBooking], $this->fetchIds('SELECT id FROM tl_resource_booking ORDER BY id'));
        $this->assertNotSame($mondayBooking, $sundayBooking);
    }

    public function testKeepsAllBookingsIfTheUserCannotNavigateBack(): void
    {
        $resource = $this->insertResource(weeksBack: 0, beginWeek: 'monday');
        $booking = $this->insertBooking($resource, '2020-01-06 10:00', '2020-01-06 11:00');

        $this->runCron();

        $this->assertSame([$booking], $this->fetchIds('SELECT id FROM tl_resource_booking'));
    }

    public function testResourceWithoutTimetableStartsTheWeekOnMonday(): void
    {
        $resource = $this->insertRow('tl_resource_booking_resource', ['title' => 'Room', 'weeksBack' => 1, 'timeSlotType' => 0]);

        $deleted = $this->insertBooking($resource, '2030-10-06 10:00', '2030-10-06 11:00');
        $kept = $this->insertBooking($resource, '2030-10-07 10:00', '2030-10-07 11:00');

        $this->runCron();

        $this->assertSame([$kept], $this->fetchIds('SELECT id FROM tl_resource_booking'));
        $this->assertNotSame($deleted, $kept);
    }

    public function testDoesNothingIfDisabled(): void
    {
        $resource = $this->insertResource(weeksBack: 1, beginWeek: 'monday');
        $booking = $this->insertBooking($resource, '2020-01-06 10:00', '2020-01-06 11:00');

        $this->runCron(enabled: false);

        $this->assertSame([$booking], $this->fetchIds('SELECT id FROM tl_resource_booking'));
    }

    private function runCron(bool $enabled = true): void
    {
        (new PurgePastBookingsCron($this->connection, $enabled, null, new MockClock(new \DateTimeImmutable(self::NOW))))();
    }

    private function insertResource(int $weeksBack, string $beginWeek): int
    {
        $timetable = $this->insertRow('tl_resource_booking_time_slot_type', ['title' => 'Timetable', 'beginWeek' => $beginWeek]);

        return $this->insertRow('tl_resource_booking_resource', ['title' => 'Room', 'weeksBack' => $weeksBack, 'timeSlotType' => $timetable]);
    }

    private function insertBooking(int $resource, string $start, string $end): int
    {
        return $this->insertRow('tl_resource_booking', ['pid' => $resource, 'startTime' => strtotime($start), 'endTime' => strtotime($end)]);
    }
}
