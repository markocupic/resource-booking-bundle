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

use Contao\Config;
use Contao\Date;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Backend\BlockedSlotCalendar;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;

class BlockedSlotCalendarTest extends ContaoTestCase
{
    public function testTheWeekStartsWithTheFirstDayOfTheTimetable(): void
    {
        $this->assertSame(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], BlockedSlotCalendar::getDaysOfWeek('monday'));
        $this->assertSame(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], BlockedSlotCalendar::getDaysOfWeek('sunday'));
        $this->assertSame(['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'], BlockedSlotCalendar::getDaysOfWeek('saturday'));
    }

    /**
     * Blocked slots with bookings are listed with a label (date and time of the time slot).
     */
    public function testReturnsTheBlockedSlotsWithBookings(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(2, 0)
        ;

        $timeSlot = $this->createMock(ResourceBookingTimeSlotModel::class);
        $timeSlot
            ->method('row')
            ->willReturn(['startTime' => 8 * 3600, 'endTime' => 9 * 3600])
        ;

        $dateAdapter = $this->createAdapterStub(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => date($format, $tstamp))
        ;

        $configAdapter = $this->createAdapterStub(['get']);
        $configAdapter
            ->method('get')
            ->willReturn('d.m.Y')
        ;

        $timeSlotAdapter = $this->createAdapterStub(['findById']);
        $timeSlotAdapter
            ->method('findById')
            ->willReturn($timeSlot)
        ;

        $framework = $this->createContaoFrameworkStub([
            Date::class => $dateAdapter,
            Config::class => $configAdapter,
            ResourceBookingTimeSlotModel::class => $timeSlotAdapter,
        ]);

        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 3]);

        $result = (new BlockedSlotCalendar($framework, $connection))->getSlotsWithBookings($resource, [
            ['timeSlot' => 7, 'date' => '2030-10-08'],
            ['timeSlot' => 7, 'date' => '2030-10-09'],
        ]);

        $this->assertSame(['7:2030-10-08' => ['bookingCount' => 2, 'label' => 'Tue 08.10.2030, 08:00 – 09:00']], $result);
    }

    public function testReturnsTheFutureSlotsOfTheOtherBlockedPeriods(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('id != ?'), ['slots', 3, 9])
            ->willReturn([
                ['id' => 4, 'tstamp' => 1, 'title' => 'Unterhalt', 'description' => '', 'published' => '1', 'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2099-10-09'], ['timeSlot' => 7, 'date' => '2000-01-01']])],
                ['id' => 5, 'tstamp' => 1, 'title' => '', 'description' => 'Anlass', 'published' => '', 'blockedSlots' => serialize([['timeSlot' => 8, 'date' => '2099-10-08']])],
            ])
        ;

        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 3]);

        $this->assertSame(
            [
                ['timeSlot' => 8, 'date' => '2099-10-08', 'title' => 'Anlass', 'published' => false],
                ['timeSlot' => 7, 'date' => '2099-10-09', 'title' => 'Unterhalt', 'published' => true],
            ],
            (new BlockedSlotCalendar($this->createContaoFrameworkStub(), $connection))->getOtherBlockedSlots($resource, 9),
        );
    }

    public function testReturnsTheLabelsOfTheTimeSlots(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 7, 'startTime' => 8 * 3600, 'endTime' => 9 * 3600],
                ['id' => 8, 'startTime' => 22 * 3600, 'endTime' => 24 * 3600],
            ])
        ;

        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 3, 'timeSlotType' => 2]);

        $this->assertSame(
            [7 => '08:00 – 09:00', 8 => '22:00 – 24:00'],
            (new BlockedSlotCalendar($this->createContaoFrameworkStub(), $connection))->getTimeSlotLabels($resource),
        );
    }
}
