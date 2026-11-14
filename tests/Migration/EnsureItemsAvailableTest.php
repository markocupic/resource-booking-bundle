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
use Markocupic\ResourceBookingBundle\Migration\EnsureItemsAvailable\EnsureItemsAvailable;
use PHPUnit\Framework\TestCase;

class EnsureItemsAvailableTest extends TestCase
{
    public function testDoesNotRunWithoutTheTable(): void
    {
        $migration = new EnsureItemsAvailable($this->connection(tableExists: false));

        $this->assertFalse($migration->shouldRun());
    }

    public function testDoesNotRunWithoutTheColumn(): void
    {
        $migration = new EnsureItemsAvailable($this->connection(columns: []));

        $this->assertFalse($migration->shouldRun());
    }

    public function testDoesNotRunIfAllResourcesHaveValidValues(): void
    {
        $migration = new EnsureItemsAvailable($this->connection(invalidCount: 0));

        $this->assertFalse($migration->shouldRun());
    }

    public function testSetsInvalidValuesToOne(): void
    {
        $connection = $this->connection(invalidCount: 2);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([3, 7])
        ;

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('SET itemsAvailable = 1 WHERE itemsAvailable IS NULL OR itemsAvailable < 1'))
            ->willReturn(2)
        ;

        $migration = new EnsureItemsAvailable($connection);

        $this->assertTrue($migration->shouldRun());

        $result = $migration->run();

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('3, 7', $result->getMessage());
    }

    /**
     * @param array<string, Column>|null $columns
     */
    private function connection(bool $tableExists = true, array|null $columns = null, int $invalidCount = 0): Connection
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager
            ->method('tablesExist')
            ->willReturn($tableExists)
        ;

        $schemaManager
            ->method('listTableColumns')
            ->willReturn($columns ?? ['itemsavailable' => $this->createStub(Column::class)])
        ;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('createSchemaManager')
            ->willReturn($schemaManager)
        ;

        $connection
            ->method('fetchOne')
            ->willReturn($invalidCount)
        ;

        return $connection;
    }
}
