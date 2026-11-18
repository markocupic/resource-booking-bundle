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
use Markocupic\ResourceBookingBundle\Migration\LegacyBlockedSlotConverter;
use Markocupic\ResourceBookingBundle\Migration\MoveBlockedBookingsToBlockedPeriods\MoveBlockedBookingsToBlockedPeriods;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MoveBlockedBookingsToBlockedPeriodsTest extends TestCase
{
    public function testDoesNotRunWithoutTheTablesOrTheColumn(): void
    {
        $this->assertFalse($this->migration($this->connection(tablesExist: false))->shouldRun());
        $this->assertFalse($this->migration($this->connection(bookingColumns: ['id']))->shouldRun());
    }

    public function testRunsIfThereAreBlockedBookings(): void
    {
        $this->assertTrue($this->migration($this->connection(found: true))->shouldRun());
        $this->assertFalse($this->migration($this->connection(found: false))->shouldRun());
    }

    /**
     * One blocked period per resource, title and description; the blocked bookings are deleted.
     */
    public function testCreatesBlockedPeriodsAndDeletesTheBlockedBookings(): void
    {
        $connection = $this->connection();
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 1, 'pid' => 3, 'title' => 'Unterhalt', 'description' => '', 'timeSlotId' => 7, 'startTime' => strtotime('2030-10-08 08:00'), 'endTime' => strtotime('2030-10-08 09:00')],
                ['id' => 2, 'pid' => 3, 'title' => 'Unterhalt', 'description' => '', 'timeSlotId' => 8, 'startTime' => strtotime('2030-10-09 09:00'), 'endTime' => strtotime('2030-10-09 10:00')],
                ['id' => 3, 'pid' => 5, 'title' => '', 'description' => 'Ferien', 'timeSlotId' => 7, 'startTime' => strtotime('2030-10-08 08:00'), 'endTime' => strtotime('2030-10-08 09:00')],
            ])
        ;

        $inserted = [];

        $connection
            ->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(
                static function (string $table, array $data) use (&$inserted): int {
                    $inserted[] = $data;

                    return 1;
                },
            )
        ;

        $statements = [];

        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql) use (&$statements): int {
                    $statements[] = $sql;

                    return 0;
                },
            )
        ;

        $result = $this->migration($connection)->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('3 blocked bookings to 2 blocked periods', $result->getMessage());

        $this->assertSame('Unterhalt', $inserted[0]['title']);
        // Without description the title is displayed in the week calendar
        $this->assertSame('Unterhalt', $inserted[0]['description']);
        $this->assertSame('slots', $inserted[0]['mode']);
        $this->assertSame(3, $inserted[0]['resource']);
        $this->assertSame(1, $inserted[0]['published']);
        $this->assertSame(strtotime('2030-10-08'), $inserted[0]['dateFrom']);
        $this->assertSame(strtotime('2030-10-09'), $inserted[0]['dateTo']);
        // Stored as time slot and date
        $this->assertSame(
            [['timeSlot' => 7, 'date' => '2030-10-08'], ['timeSlot' => 8, 'date' => '2030-10-09']],
            BlockedSlots::fromPeriod(['blockedSlots' => $inserted[0]['blockedSlots']]),
        );

        $this->assertSame(MoveBlockedBookingsToBlockedPeriods::DEFAULT_TITLE, $inserted[1]['title']);
        $this->assertSame('Ferien', $inserted[1]['description']);
        $this->assertSame(5, $inserted[1]['resource']);

        // The new columns are created (the migration runs before the database update)
        $this->assertCount(3, array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'ALTER TABLE tl_resource_booking_blocked_period ADD')));
        $this->assertContains('DELETE FROM tl_resource_booking WHERE isBlocked = 1', $statements);
    }

    private function migration(Connection $connection): MoveBlockedBookingsToBlockedPeriods
    {
        return new MoveBlockedBookingsToBlockedPeriods($connection, new LegacyBlockedSlotConverter($connection));
    }

    /**
     * @param list<string> $bookingColumns
     */
    private function connection(bool $tablesExist = true, array $bookingColumns = ['id', 'isblocked'], bool $found = true): Connection&MockObject
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tablesExist)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturnCallback(fn (string $table): array => 'tl_resource_booking' === $table
                ? array_fill_keys($bookingColumns, $this->createStub(Column::class))
                : array_fill_keys(['id', 'title'], $this->createStub(Column::class)))
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
