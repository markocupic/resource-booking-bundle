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

namespace Markocupic\ResourceBookingBundle\Migration\BlockedPeriodTimeAsInteger;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\StringType;

/**
 * A pre-release version stored tl_resource_booking_blocked_period.timeFrom/timeTo
 * as strings ("hh:mm"). They are now Contao time values (integer, time picker).
 * Converting an empty string to an integer fails in strict mode, so the columns
 * are converted here; the times are kept.
 */
class BlockedPeriodTimeAsInteger extends AbstractMigration
{
    private const TABLE = 'tl_resource_booking_blocked_period';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Convert the times of the blocked periods into Contao time values';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns(self::TABLE);

        return isset($columns['timefrom']) && $columns['timefrom']->getType() instanceof StringType;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT id, timeFrom, timeTo FROM %s', self::TABLE));

        foreach (['timeFrom', 'timeTo'] as $column) {
            $this->connection->executeStatement(\sprintf('ALTER TABLE %s MODIFY %s VARCHAR(16) NULL', self::TABLE, $column));
            $this->connection->executeStatement(\sprintf('UPDATE %s SET %s = NULL', self::TABLE, $column));
            $this->connection->executeStatement(\sprintf('ALTER TABLE %s MODIFY %s INT NULL', self::TABLE, $column));
        }

        // Keep the times: "hh:mm" -> timestamp of 1970-01-01 in the local time zone (as Contao stores rgxp "time")
        foreach ($rows as $row) {
            $set = [];

            foreach (['timeFrom', 'timeTo'] as $column) {
                $value = trim((string) $row[$column]);
                $set[$column] = preg_match('/^\d{1,2}:\d{2}$/', $value) ? mktime(...array_map('intval', [...explode(':', $value), 0, 1, 1, 1970])) : null;
            }

            $this->connection->update(self::TABLE, $set, ['id' => (int) $row['id']]);
        }

        return $this->createResult(true, 'Converted the times of the blocked periods into Contao time values.');
    }
}
