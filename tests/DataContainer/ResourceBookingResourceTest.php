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

namespace Markocupic\ResourceBookingBundle\Tests\DataContainer;

use Contao\DataContainer;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingResource;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingResourceTest extends ContaoTestCase
{
    public function testChildRecordCallbackRendersTheTitle(): void
    {
        $dca = new ResourceBookingResource($this->createContaoFrameworkStub(), $this->createStub(Connection::class), $this->translator());

        $this->assertSame(
            '<div class="tl_content_left">Room 1</div>',
            $dca->childRecordCallback(['title' => 'Room 1']),
        );
    }

    public function testCountsFutureBookingsThatDoNotFitTheTimetable(): void
    {
        $monday = strtotime('next monday');

        $bookings = [
            // Fits the time slot 08:00 - 09:00
            ['id' => 1, 'startTime' => $monday + 8 * 3600, 'endTime' => $monday + 9 * 3600],
            // No time slot 10:00 - 11:00
            ['id' => 2, 'startTime' => $monday + 10 * 3600, 'endTime' => $monday + 11 * 3600],
            // The time slot 08:00 - 09:00 is not available on tuesdays
            ['id' => 3, 'startTime' => $monday + 86400 + 8 * 3600, 'endTime' => $monday + 86400 + 9 * 3600],
        ];

        $timeSlots = [
            ['startTime' => 8 * 3600, 'endTime' => 9 * 3600, 'weekdays' => serialize(['monday'])],
        ];

        $dca = new ResourceBookingResource($this->createContaoFrameworkStub(), $this->connection(['id' => 3, 'timeSlotType' => 4], $bookings, $timeSlots), $this->translator());

        $this->assertSame(2, $dca->countFutureBookingsNotMatchingTheTimetable(3));
    }

    public function testBookingsOnWeekdaysTheResourceCannotBeBookedDoNotFit(): void
    {
        $monday = strtotime('next monday');

        $bookings = [
            ['id' => 1, 'startTime' => $monday + 8 * 3600, 'endTime' => $monday + 9 * 3600],
        ];

        $timeSlots = [
            ['startTime' => 8 * 3600, 'endTime' => 9 * 3600, 'weekdays' => null],
        ];

        $resource = ['id' => 3, 'timeSlotType' => 4, 'restrictWeekdays' => '1', 'allowedWeekdays' => serialize(['tuesday'])];

        $dca = new ResourceBookingResource($this->createContaoFrameworkStub(), $this->connection($resource, $bookings, $timeSlots), $this->translator());

        $this->assertSame(1, $dca->countFutureBookingsNotMatchingTheTimetable(3));
    }

    public function testShowsAWarningOnSubmit(): void
    {
        $monday = strtotime('next monday');

        $bookings = [
            ['id' => 2, 'startTime' => $monday + 10 * 3600, 'endTime' => $monday + 11 * 3600],
        ];

        $messageAdapter = $this->createAdapterMock(['addInfo']);
        $messageAdapter
            ->expects($this->once())
            ->method('addInfo')
            ->with('RBB.BE.futureBookingsNotMatchingTimetable:1')
        ;

        $dca = new ResourceBookingResource(
            $this->createContaoFrameworkStub([Message::class => $messageAdapter]),
            $this->connection(['id' => 3, 'timeSlotType' => 4], $bookings, []),
            $this->translator(),
        );

        $dca->warnAboutFutureBookingsNotMatchingTheTimetable($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 3]));
    }

    public function testShowsNoWarningWithoutFutureBookings(): void
    {
        $messageAdapter = $this->createAdapterMock(['addInfo']);
        $messageAdapter
            ->expects($this->never())
            ->method('addInfo')
        ;

        $dca = new ResourceBookingResource(
            $this->createContaoFrameworkStub([Message::class => $messageAdapter]),
            $this->connection(['id' => 3, 'timeSlotType' => 4], [], []),
            $this->translator(),
        );

        $dca->warnAboutFutureBookingsNotMatchingTheTimetable($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 3]));
    }

    /**
     * @param array<string, mixed>             $resource
     * @param array<int, array<string, mixed>> $bookings
     * @param array<int, array<string, mixed>> $timeSlots
     */
    private function connection(array $resource, array $bookings, array $timeSlots): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn($resource)
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturnCallback(static fn (string $sql): array => str_contains($sql, 'FROM tl_resource_booking WHERE') ? $bookings : $timeSlots)
        ;

        return $connection;
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id, array $parameters = []): string => $id.($parameters ? ':'.implode(',', $parameters) : ''))
        ;

        return $translator;
    }
}
