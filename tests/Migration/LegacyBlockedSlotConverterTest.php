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

namespace Markocupic\ResourceBookingBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Migration\LegacyBlockedSlotConverter;
use PHPUnit\Framework\TestCase;

class LegacyBlockedSlotConverterTest extends TestCase
{
    public function testConvertsStartAndEndTimeIntoTimeSlotAndDate(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 7, 'startTime' => 8 * 3600, 'endTime' => 9 * 3600],
                ['id' => 8, 'startTime' => 9 * 3600, 'endTime' => 10 * 3600],
            ])
        ;

        $slots = (new LegacyBlockedSlotConverter($connection))->convert(3, [
            // With time slot
            ['timeSlot' => 12, 'startTime' => strtotime('2030-10-09 14:00'), 'endTime' => strtotime('2030-10-09 15:00')],
            // Without time slot: the time slot with the same times is used
            ['timeSlot' => 0, 'startTime' => strtotime('2030-10-08 09:00'), 'endTime' => strtotime('2030-10-08 10:00')],
            // No time slot with these times: skipped
            ['timeSlot' => 0, 'startTime' => strtotime('2030-10-08 11:00'), 'endTime' => strtotime('2030-10-08 12:00')],
            // Already converted
            ['timeSlot' => 7, 'date' => '2030-10-10'],
        ]);

        $this->assertSame(
            [
                ['timeSlot' => 8, 'date' => '2030-10-08'],
                ['timeSlot' => 12, 'date' => '2030-10-09'],
                ['timeSlot' => 7, 'date' => '2030-10-10'],
            ],
            $slots,
        );
    }
}
