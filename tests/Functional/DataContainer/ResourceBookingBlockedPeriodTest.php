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

namespace Markocupic\ResourceBookingBundle\Tests\Functional\DataContainer;

use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingBlockedPeriod;
use Markocupic\ResourceBookingBundle\Restriction\BlockedPeriod;
use Markocupic\ResourceBookingBundle\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingBlockedPeriodTest extends FunctionalTestCase
{
    private const NOW = '2030-10-16 12:00';

    public function testCountsOnlyFutureBookingsOfAffectedResourcesInThePeriod(): void
    {
        $rooms = $this->insertRow('tl_resource_booking_resource_type', ['title' => 'Räume']);
        $computers = $this->insertRow('tl_resource_booking_resource_type', ['title' => 'Computer']);
        $room = $this->insertRow('tl_resource_booking_resource', ['pid' => $rooms, 'title' => 'Werkraum']);
        $notebooks = $this->insertRow('tl_resource_booking_resource', ['pid' => $computers, 'title' => 'Notebooks']);

        // Counted: future booking of a room within the period
        $this->insertBooking($room, '2030-10-21 08:00', '2030-10-21 09:00');
        // Counted: last day of the period
        $this->insertBooking($room, '2030-10-25 16:00', '2030-10-25 17:00');
        // Not counted: already over
        $this->insertBooking($room, '2030-10-16 08:00', '2030-10-16 09:00');
        // Not counted: after the period
        $this->insertBooking($room, '2030-10-26 08:00', '2030-10-26 09:00');
        // Not counted: another resource type
        $this->insertBooking($notebooks, '2030-10-21 08:00', '2030-10-21 09:00');

        $period = BlockedPeriod::fromRow([
            'published' => '1',
            'dateFrom' => strtotime('2030-10-14'),
            'dateTo' => strtotime('2030-10-25'),
            'scope' => 'resourceTypes',
            'resourceTypes' => (string) $rooms,
        ]);

        $this->assertSame(2, $this->createDca()->countFutureBookingsInBlockedPeriod($period));
    }

    public function testPeriodWithoutEndDateCountsAllFutureBookings(): void
    {
        $room = $this->insertRow('tl_resource_booking_resource', ['pid' => 1, 'title' => 'Werkraum']);

        $this->insertBooking($room, '2030-10-21 08:00', '2030-10-21 09:00');
        $this->insertBooking($room, '2045-06-01 08:00', '2045-06-01 09:00');

        $period = BlockedPeriod::fromRow(['published' => '1', 'dateFrom' => strtotime('2030-10-14'), 'dateTo' => 0, 'scope' => 'all']);

        $this->assertSame(2, $this->createDca()->countFutureBookingsInBlockedPeriod($period));
    }

    public function testCountsBookingsOfBlockedSlots(): void
    {
        $room = $this->insertRow('tl_resource_booking_resource', ['pid' => 1, 'title' => 'Werkraum']);
        $otherRoom = $this->insertRow('tl_resource_booking_resource', ['pid' => 1, 'title' => 'Turnhalle']);

        // Counted: the blocked time slot
        $this->insertBooking($room, '2030-10-21 08:00', '2030-10-21 09:00', timeSlotId: 7);
        // Not counted: another time slot, another resource
        $this->insertBooking($room, '2030-10-21 09:00', '2030-10-21 10:00', timeSlotId: 8);
        $this->insertBooking($otherRoom, '2030-10-21 08:00', '2030-10-21 09:00', timeSlotId: 7);

        $period = BlockedPeriod::fromRow([
            'published' => '1',
            'mode' => 'slots',
            'resource' => $room,
            'dateFrom' => strtotime('2030-10-21'),
            'dateTo' => strtotime('2030-10-21'),
            'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2030-10-21']]),
        ]);

        $this->assertSame(1, $this->createDca()->countFutureBookingsInBlockedPeriod($period));
    }

    private function createDca(): ResourceBookingBlockedPeriod
    {
        return new ResourceBookingBlockedPeriod(
            $this->createContaoFrameworkStub(),
            $this->connection,
            $this->createStub(TranslatorInterface::class),
            null,
            new MockClock(new \DateTimeImmutable(self::NOW)),
        );
    }

    private function insertBooking(int $resource, string $start, string $end, int $timeSlotId = 0): void
    {
        $this->insertRow('tl_resource_booking', ['pid' => $resource, 'timeSlotId' => $timeSlotId, 'startTime' => strtotime($start), 'endTime' => strtotime($end)]);
    }
}
