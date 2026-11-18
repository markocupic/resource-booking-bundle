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

namespace Markocupic\ResourceBookingBundle\Tests\WeekCalendar;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Slot\Slot;
use Markocupic\ResourceBookingBundle\WeekCalendar\SlotPresenter;

class SlotPresenterTest extends ContaoTestCase
{
    public function testSendsOnlyThePermittedKeys(): void
    {
        $startTime = strtotime('tomorrow 08:00');
        $slot = new Slot(
            timeSlotId: 4,
            resource: ['id' => 3, 'itemsAvailable' => 2, 'title' => 'Room', 'internalNote' => 'secret'],
            startTime: $startTime,
            endTime: $startTime + 3600,
            now: time(),
            loggedInMemberId: 5,
            isValidTimeSlot: true,
            bookings: [
                ['id' => 30, 'member' => 5, 'itemsBooked' => 1, 'bookingUuid' => 'abc', 'phone' => '079'],
                ['id' => 31, 'member' => 9, 'itemsBooked' => 1, 'bookingUuid' => 'def', 'phone' => '078'],
            ],
        );

        $data = (new SlotPresenter($this->createFramework()))->present($slot);

        $this->assertSame(
            [
                'timeSlotId', 'resourceId', 'startTime', 'endTime',
                'isBookable', 'isValidTimeSlot', 'hasValidItemCount', 'isInBookingWindow', 'isAvailableOnWeekday',
                'isInBlockedPeriod', 'blockedPeriodDescription', 'canFulfillRequestedItems', 'remainingItems',
                'isFullyBooked', 'hasBookings', 'isMemberLoggedIn', 'memberBooking', 'isCancelable',
                'startTimeString', 'endTimeString', 'date', 'datimSpanString', 'timeSpanString',
            ],
            array_keys($data),
        );

        $this->assertSame(3, $data['resourceId']);
        $this->assertSame(0, $data['remainingItems']);
        $this->assertTrue($data['isFullyBooked']);
        // Own booking: only the IDs for the cancellation
        $this->assertSame(['id' => 30, 'bookingUuid' => 'abc'], $data['memberBooking']);
        $this->assertTrue($data['isCancelable']);
        $this->assertSame(date('d.m.Y', $startTime), $data['date']);
    }

    public function testGuestHasNoMemberBooking(): void
    {
        $startTime = strtotime('tomorrow 08:00');
        $slot = new Slot(timeSlotId: 4, resource: ['id' => 3, 'itemsAvailable' => 1], startTime: $startTime, endTime: $startTime + 3600, now: time(), isValidTimeSlot: true);

        $data = (new SlotPresenter($this->createFramework()))->present($slot);

        $this->assertFalse($data['isMemberLoggedIn']);
        $this->assertNull($data['memberBooking']);
        $this->assertTrue($data['isBookable']);
        $this->assertSame(1, $data['remainingItems']);
    }

    private function createFramework(): ContaoFramework
    {
        $configAdapter = $this->createAdapterStub(['get']);
        $configAdapter
            ->method('get')
            ->willReturn('d.m.Y')
        ;

        $dateAdapter = $this->createAdapterStub(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => date($format, $tstamp))
        ;

        return $this->createContaoFrameworkStub([
            Config::class => $configAdapter,
            Date::class => $dateAdapter,
        ]);
    }
}
