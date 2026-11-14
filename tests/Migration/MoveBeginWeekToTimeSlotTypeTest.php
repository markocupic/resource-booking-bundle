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
use Markocupic\ResourceBookingBundle\Migration\MoveBeginWeekToTimeSlotType\MoveBeginWeekToTimeSlotType;
use PHPUnit\Framework\TestCase;

class MoveBeginWeekToTimeSlotTypeTest extends TestCase
{
    public function testDoesNotRunIfTheColumnAlreadyExists(): void
    {
        $migration = new MoveBeginWeekToTimeSlotType($this->connection(beginWeekExists: true));

        $this->assertFalse($migration->shouldRun());
    }

    public function testWaitsForTheRenamedModuleFields(): void
    {
        $migration = new MoveBeginWeekToTimeSlotType($this->connection(appConfigExists: false));

        $this->assertFalse($migration->shouldRun());
    }

    public function testDoesNotRunWithoutTheTables(): void
    {
        $migration = new MoveBeginWeekToTimeSlotType($this->connection(tablesExist: false));

        $this->assertFalse($migration->shouldRun());
    }

    public function testCopiesTheFirstDayOfTheWeekToTheTimetables(): void
    {
        $connection = $this->connection();

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('ADD beginWeek'))
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                // Resource type 1 -> resource 10 -> timetable 4
                ['resourceBooking_appConfig' => 'sunday_app', 'resourceBooking_resourceTypes' => serialize(['1'])],
                // Resource type 2 -> resource 20 -> timetable 5 (monday, no update)
                ['resourceBooking_appConfig' => '', 'resourceBooking_resourceTypes' => serialize(['2'])],
                // Resource type 3 -> resource 30 -> timetable 4 again (sunday)
                ['resourceBooking_appConfig' => 'sunday_app', 'resourceBooking_resourceTypes' => serialize(['3'])],
            ])
        ;

        $connection
            ->method('fetchFirstColumn')
            ->willReturnCallback(static function (string $sql, array $params): array {
                if (str_contains($sql, 'SELECT id FROM tl_resource_booking_resource')) {
                    return [match ($params[0]) {
                        [1] => 10,
                        [2] => 20,
                        default => 30,
                    }];
                }

                return [match ($params[0]) {
                    [20] => 5,
                    default => 4,
                }];
            })
        ;

        $connection
            ->expects($this->once())
            ->method('update')
            ->with('tl_resource_booking_time_slot_type', ['beginWeek' => 'sunday'], ['id' => 4])
        ;

        $migration = new MoveBeginWeekToTimeSlotType($connection, [
            'default' => ['permitted_form_fields' => []],
            'sunday_app' => ['begin_week' => 'sunday'],
        ]);

        $this->assertTrue($migration->shouldRun());

        $result = $migration->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('4 (sunday)', $result->getMessage());
        $this->assertStringContainsString('can now be removed from the app configurations: sunday_app', $result->getMessage());
        $this->assertStringNotContainsString('please check', $result->getMessage());
    }

    public function testReportsTimetablesUsedWithDifferentValues(): void
    {
        $connection = $this->connection();

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['resourceBooking_appConfig' => 'sunday_app', 'resourceBooking_resourceTypes' => serialize(['1'])],
                ['resourceBooking_appConfig' => 'default', 'resourceBooking_resourceTypes' => serialize(['1'])],
                ['resourceBooking_appConfig' => 'default', 'resourceBooking_resourceTypes' => serialize(['1'])],
            ])
        ;

        $connection
            ->method('fetchFirstColumn')
            ->willReturnCallback(static fn (string $sql): array => str_contains($sql, 'SELECT id FROM') ? [10] : [4])
        ;

        // Monday is the most frequent value -> no update
        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $migration = new MoveBeginWeekToTimeSlotType($connection, [
            'default' => [],
            'sunday_app' => ['begin_week' => 'sunday'],
        ]);

        $result = $migration->run();

        $this->assertStringContainsString('please check: 4', $result->getMessage());
    }

    private function connection(bool $tablesExist = true, bool $appConfigExists = true, bool $beginWeekExists = false): Connection
    {
        $column = $this->createStub(Column::class);

        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tablesExist)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturnCallback(static fn (string $table): array => match ($table) {
                'tl_module' => $appConfigExists ? ['resourcebooking_appconfig' => $column] : [],
                'tl_resource_booking_resource' => ['timeslottype' => $column],
                'tl_resource_booking_time_slot_type' => $beginWeekExists ? ['beginweek' => $column] : [],
                default => [],
            })
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        return $connection;
    }
}
