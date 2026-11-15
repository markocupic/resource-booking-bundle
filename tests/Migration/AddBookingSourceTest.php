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
use Markocupic\ResourceBookingBundle\Booking\BookingSource;
use Markocupic\ResourceBookingBundle\Migration\AddBookingSource\AddBookingSource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddBookingSourceTest extends TestCase
{
    public function testDoesNotRunWithoutTheTableOrColumn(): void
    {
        $this->assertFalse((new AddBookingSource($this->connection(tableExists: false)))->shouldRun());
        $this->assertFalse((new AddBookingSource($this->connection(columns: ['member'])))->shouldRun());
    }

    public function testRunsIfBookingsOfMembersHaveNoSource(): void
    {
        $this->assertTrue((new AddBookingSource($this->connection(found: true)))->shouldRun());
        $this->assertFalse((new AddBookingSource($this->connection(found: false)))->shouldRun());
    }

    public function testSetsTheSourceWeekCalendar(): void
    {
        $connection = $this->connection(found: true);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains("WHERE bookingSource = '' AND member > 0"), [BookingSource::WEEK_CALENDAR])
            ->willReturn(3)
        ;

        $result = (new AddBookingSource($connection))->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('3 bookings', $result->getMessage());
    }

    /**
     * @param list<string> $columns
     */
    private function connection(bool $tableExists = true, array $columns = ['bookingsource', 'member'], bool $found = true): Connection&MockObject
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tableExists)
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
            ->willReturn($found ? 1 : false)
        ;

        return $connection;
    }
}
