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
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingTimeSlotType;

class ResourceBookingTimeSlotTypeTest extends ContaoTestCase
{
    public function testRemoveChildRecordsDoesNothingWithoutAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeStatement')
        ;

        $dc = $this->mockClassWithProperties(DataContainer::class, ['id' => 0]);

        (new ResourceBookingTimeSlotType($connection))->removeChildRecords($dc);
    }

    public function testRemoveChildRecordsDeletesBookingsAndTimeSlots(): void
    {
        $calls = [];

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params) use (&$calls): int {
                    $calls[] = ['sql' => $sql, 'params' => $params];

                    return 0;
                },
            )
        ;

        $dc = $this->mockClassWithProperties(DataContainer::class, ['id' => 7]);

        (new ResourceBookingTimeSlotType($connection))->removeChildRecords($dc);

        // One statement deletes the child bookings, the other the time slots themselves.
        $this->assertCount(2, $calls);

        foreach ($calls as $call) {
            $this->assertSame([7], $call['params']);
        }

        $this->assertStringContainsString('DELETE FROM tl_resource_booking', $calls[0]['sql']);
        $this->assertStringContainsString('DELETE FROM tl_resource_booking_time_slot WHERE pid', $calls[1]['sql']);
    }
}
