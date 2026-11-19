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

namespace Markocupic\ResourceBookingBundle\Migration\ConvertSerializedListsToCsv;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Util\CsvList;

/**
 * Up to version 5, the lists of values were stored as serialized arrays. Since
 * version 6 they are stored as comma-separated values (DCA: 'eval' => ['csv' => ',']),
 * e.g. a:2:{i:0;s:6:"monday";i:1;s:7:"tuesday";} becomes "monday,tuesday".
 *
 * The migration runs before and after the schema update: the values can be converted
 * in the old BLOB columns as well as in the new TEXT columns.
 */
class ConvertSerializedListsToCsv extends AbstractMigration
{
    /**
     * Fields per table.
     */
    public const FIELDS = [
        'tl_module' => ['resourceBooking_resourceTypes', 'resourceBooking_clientPersonalData', 'resourceBooking_bookingDetailFields'],
        'tl_resource_booking_blocked_period' => ['resourceTypes', 'resources'],
        'tl_resource_booking_time_slot' => ['weekdays'],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Store the lists of values as comma-separated values instead of serialized arrays';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        foreach ($this->getExistingFields() as $table => $fields) {
            foreach ($fields as $field) {
                if (false !== $this->connection->fetchOne(\sprintf("SELECT id FROM %s WHERE %s LIKE 'a:%%' LIMIT 1", $table, $this->connection->quoteSingleIdentifier($field)))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $count = 0;

        foreach ($this->getExistingFields() as $table => $fields) {
            foreach ($fields as $field) {
                $column = $this->connection->quoteSingleIdentifier($field);
                $rows = $this->connection->fetchAllAssociative(\sprintf("SELECT id, %s AS value FROM %s WHERE %s LIKE 'a:%%'", $column, $table, $column));

                foreach ($rows as $row) {
                    $this->connection->update($table, [$column => CsvList::toString(CsvList::parse($row['value']))], ['id' => (int) $row['id']]);
                    ++$count;
                }
            }
        }

        return $this->createResult(true, \sprintf('Converted %d serialized list(s) into comma-separated values.', $count));
    }

    /**
     * @return array<string, list<string>>
     *
     * @throws Exception
     */
    private function getExistingFields(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $existing = [];

        foreach (self::FIELDS as $table => $fields) {
            if (!$schemaManager->tablesExist([$table])) {
                continue;
            }

            $columns = $schemaManager->listTableColumns($table);
            $existing[$table] = array_values(array_filter($fields, static fn (string $field): bool => isset($columns[strtolower($field)])));
        }

        return array_filter($existing);
    }
}
