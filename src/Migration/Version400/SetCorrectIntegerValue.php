<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Migration\Version400;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Convert fields witch type char(1) to tinyint(1)
 * and set the correct value.
 */
class SetCorrectIntegerValue extends AbstractMigration
{
    private array $data = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        $tables = [
            'tl_resource_booking_resource',
            'tl_resource_booking_resource_type',
            'tl_resource_booking_time_slot',
            'tl_resource_booking_time_slot_type',
        ];

        $this->data = [];

        $doMigration = false;

        // If the database table itself does not exist we should do nothing
        if (!$schemaManager->tablesExist($tables)) {
            return false;
        }

        foreach ($tables as $tableName) {
            $columns = $schemaManager->listTableColumns($tableName);
            $this->data[$tableName] = [];

            if (isset($columns['published'])) {
                $result = $this->connection->executeQuery('SELECT id,published FROM '.$tableName);

                while (false !== ($record = $result->fetchAssociative())) {
                    if ('string' === \gettype($record['published'])) {
                        $doMigration = true;

                        if ($record['published']) {
                            $this->data[$tableName][] = ['id' => $record['id'], 'published' => 1];
                        } else {
                            $this->data[$tableName][] = ['id' => $record['id'], 'published' => 0];
                        }
                    }
                }
            }
        }

        return $doMigration;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        foreach (array_keys($this->data) as $tableName) {
            $this->connection->executeStatement('ALTER TABLE '.$tableName.' MODIFY published TINYINT(1) NOT NULL');

            foreach ($this->data[$tableName] as $record) {
                $set = [];
                $set['published'] = $record['published'];
                $this->connection->update($tableName, $set, ['id' => $record['id']]);
            }
        }

        return new MigrationResult(
            true,
            'Set correct integer value after converting boolean fields from type "char(1)" to "tinyint(1)".'
        );
    }
}
