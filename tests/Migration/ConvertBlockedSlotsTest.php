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
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Markocupic\ResourceBookingBundle\Migration\ConvertBlockedSlots\ConvertBlockedSlots;
use Markocupic\ResourceBookingBundle\Migration\LegacyBlockedSlotConverter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConvertBlockedSlotsTest extends TestCase
{
    public function testDoesNotRunWithoutTheColumnsOrWithoutLegacySlots(): void
    {
        $this->assertFalse($this->migration($this->connection(columns: ['mode']))->shouldRun());
        $this->assertFalse($this->migration($this->connection(found: false))->shouldRun());
        $this->assertTrue($this->migration($this->connection(found: true))->shouldRun());
    }

    public function testConvertsTheSlotsAndUpdatesTheDateRange(): void
    {
        $connection = $this->connection();
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 4,
                    'resource' => 3,
                    'dateFrom' => 0,
                    'blockedSlots' => serialize([
                        ['timeSlot' => 7, 'startTime' => strtotime('2030-10-09 08:00'), 'endTime' => strtotime('2030-10-09 09:00')],
                        ['timeSlot' => 7, 'startTime' => strtotime('2030-10-08 08:00'), 'endTime' => strtotime('2030-10-08 09:00')],
                    ]),
                ],
            ])
        ;

        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'tl_resource_booking_blocked_period',
                [
                    'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2030-10-08'], ['timeSlot' => 7, 'date' => '2030-10-09']]),
                    'dateFrom' => strtotime('2030-10-08'),
                    'dateTo' => strtotime('2030-10-09'),
                ],
                ['id' => 4],
            )
        ;

        $this->assertTrue($this->migration($connection)->run()->isSuccessful());
    }

    private function migration(Connection $connection): ConvertBlockedSlots
    {
        return new ConvertBlockedSlots($connection, new LegacyBlockedSlotConverter($connection));
    }

    /**
     * @param list<string> $columns
     */
    private function connection(array $columns = ['mode', 'resource', 'blockedslots'], bool $found = true): Connection&MockObject
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn(true)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturn(array_fill_keys($columns, $this->createStub(Column::class)))
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $connection
            ->method('fetchOne')
            ->willReturn($found ? 4 : false)
        ;

        return $connection;
    }
}
