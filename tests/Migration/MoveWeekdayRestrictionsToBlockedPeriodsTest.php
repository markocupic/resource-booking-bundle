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
use Markocupic\ResourceBookingBundle\Migration\MoveWeekdayRestrictionsToBlockedPeriods\MoveWeekdayRestrictionsToBlockedPeriods;
use PHPUnit\Framework\TestCase;

class MoveWeekdayRestrictionsToBlockedPeriodsTest extends TestCase
{
    public function testDoesNotRunWithoutTheOldColumns(): void
    {
        $migration = new MoveWeekdayRestrictionsToBlockedPeriods($this->connection(['id' => $this->createStub(Column::class)], 1));

        $this->assertFalse($migration->shouldRun());
    }

    public function testDoesNotRunWithoutRestrictedResources(): void
    {
        $migration = new MoveWeekdayRestrictionsToBlockedPeriods($this->connection(null, 0));

        $this->assertFalse($migration->shouldRun());
    }

    public function testCreatesOneBlockedPeriodPerSetOfBlockedWeekdays(): void
    {
        $inserts = [];

        $connection = $this->connection(null, 3);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 1, 'title' => 'Werkraum', 'allowedWeekdays' => serialize(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
                ['id' => 2, 'title' => 'Turnhalle', 'allowedWeekdays' => serialize(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
                ['id' => 3, 'title' => 'Aula', 'allowedWeekdays' => serialize(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            ])
        ;

        $connection
            ->method('insert')
            ->willReturnCallback(
                static function (string $table, array $data) use (&$inserts): int {
                    $inserts[] = $data;

                    return 1;
                },
            )
        ;

        $connection
            ->method('lastInsertId')
            ->willReturn('9')
        ;

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('SET restrictWeekdays = 0'))
        ;

        $migration = new MoveWeekdayRestrictionsToBlockedPeriods($connection);

        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());

        $this->assertCount(2, $inserts);

        // Werkraum and Turnhalle: saturday and sunday
        $this->assertSame('Nicht buchbare Wochentage: Samstag, Sonntag', $inserts[0]['title']);
        $this->assertSame('Nicht buchbar', $inserts[0]['description']);
        $this->assertSame(['1', '2'], unserialize($inserts[0]['resources']));
        $this->assertSame('resources', $inserts[0]['scope']);
        $this->assertSame(0, $inserts[0]['dateTo']);
        $this->assertSame(
            [
                ['weekday' => 'saturday', 'timeFrom' => '', 'timeTo' => ''],
                ['weekday' => 'sunday', 'timeFrom' => '', 'timeTo' => ''],
            ],
            unserialize($inserts[0]['blockedTimes']),
        );

        // Aula: sunday
        $this->assertSame(['3'], unserialize($inserts[1]['resources']));
        $this->assertSame([['weekday' => 'sunday', 'timeFrom' => '', 'timeTo' => '']], unserialize($inserts[1]['blockedTimes']));
    }

    /**
     * @param array<string, Column>|null $resourceColumns
     */
    private function connection(array|null $resourceColumns, int $restrictedCount): Connection
    {
        $column = $this->createStub(Column::class);

        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn(true)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturnCallback(
                static fn (string $table): array => 'tl_resource_booking_resource' === $table
                    ? ($resourceColumns ?? ['restrictweekdays' => $column, 'allowedweekdays' => $column])
                    : ['blockedtimes' => $column],
            )
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $connection
            ->method('fetchOne')
            ->willReturn($restrictedCount)
        ;

        return $connection;
    }
}
