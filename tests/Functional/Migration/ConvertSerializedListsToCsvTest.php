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

namespace Markocupic\ResourceBookingBundle\Tests\Functional\Migration;

use Markocupic\ResourceBookingBundle\Migration\ConvertSerializedListsToCsv\ConvertSerializedListsToCsv;
use Markocupic\ResourceBookingBundle\Tests\Functional\FunctionalTestCase;

class ConvertSerializedListsToCsvTest extends FunctionalTestCase
{
    public function testDoesNotRunWithoutSerializedValues(): void
    {
        $this->insertRow('tl_resource_booking_time_slot', ['title' => 'Lektion', 'weekdays' => 'monday,tuesday']);
        $this->insertRow('tl_resource_booking_blocked_period', ['title' => 'Ferien', 'resources' => '3,4']);

        $this->assertFalse((new ConvertSerializedListsToCsv($this->connection))->shouldRun());
    }

    public function testConvertsTheSerializedListsIntoCsv(): void
    {
        $timeSlot = $this->insertRow('tl_resource_booking_time_slot', ['title' => 'Lektion', 'weekdays' => serialize(['monday', 'tuesday'])]);
        $migrated = $this->insertRow('tl_resource_booking_time_slot', ['title' => 'Lektion', 'weekdays' => 'friday']);
        $period = $this->insertRow('tl_resource_booking_blocked_period', [
            'title' => 'Ferien',
            'resourceTypes' => serialize(['2']),
            'resources' => serialize(['3', '4']),
        ]);
        $empty = $this->insertRow('tl_resource_booking_blocked_period', ['title' => 'Leer', 'resources' => serialize([])]);

        $migration = new ConvertSerializedListsToCsv($this->connection);

        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());
        $this->assertFalse($migration->shouldRun());

        $this->assertSame('monday,tuesday', $this->fetchValue('tl_resource_booking_time_slot', 'weekdays', $timeSlot));
        $this->assertSame('friday', $this->fetchValue('tl_resource_booking_time_slot', 'weekdays', $migrated));
        $this->assertSame('2', $this->fetchValue('tl_resource_booking_blocked_period', 'resourceTypes', $period));
        $this->assertSame('3,4', $this->fetchValue('tl_resource_booking_blocked_period', 'resources', $period));
        $this->assertSame('', $this->fetchValue('tl_resource_booking_blocked_period', 'resources', $empty));
    }

    public function testSkipsMissingTablesAndColumns(): void
    {
        // tl_module is not part of the tables of the functional tests
        $this->connection->executeStatement('DROP TABLE tl_resource_booking_time_slot');

        $this->insertRow('tl_resource_booking_blocked_period', ['title' => 'Ferien', 'resources' => serialize(['3'])]);

        $migration = new ConvertSerializedListsToCsv($this->connection);

        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());
        $this->assertFalse($migration->shouldRun());
    }

    private function fetchValue(string $table, string $field, int $id): string
    {
        return (string) $this->connection->fetchOne(\sprintf('SELECT %s FROM %s WHERE id = ?', $field, $table), [$id]);
    }
}
