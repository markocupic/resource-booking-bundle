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

namespace Markocupic\ResourceBookingBundle\Migration\RenameModuleFields;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Renames the following columns of tl_module:
 *
 *   resourceBooking_setBookingSubmittedFields -> resourceBooking_displayBookingDetails
 *   resourceBooking_bookingSubmittedFields -> resourceBooking_bookingDetailFields
 *
 * If the schema update has already added the new columns, the values are copied
 * and the old columns are dropped. Otherwise, the old columns are renamed.
 */
class RenameModuleFields extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Rename tl_module.resourceBooking_setBookingSubmittedFields/bookingSubmittedFields';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_module'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_module');

        foreach (array_keys($this->getColumns()) as $oldName) {
            if (isset($columns[strtolower($oldName)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_module');
        $renamed = [];

        foreach ($this->getColumns() as $oldName => [$newName, $definition]) {
            if (!isset($columns[strtolower($oldName)])) {
                continue;
            }

            if (isset($columns[strtolower($newName)])) {
                // The schema update has already added the new column: copy the values and drop the old column.
                $this->connection->executeStatement("UPDATE tl_module SET $newName = $oldName");
                $this->connection->executeStatement("ALTER TABLE tl_module DROP $oldName");
            } else {
                $this->connection->executeStatement("ALTER TABLE tl_module CHANGE $oldName $newName $definition");
            }

            $renamed[] = $oldName.' -> '.$newName;
        }

        return $this->createResult(true, 'Renamed the columns '.implode(', ', $renamed).' in tl_module.');
    }

    /**
     * Returns the old column names with the new column name and its definition
     * (as defined in contao/dca/tl_module.php).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function getColumns(): array
    {
        $defaultBookingDetailFields = serialize(['title', 'description']);

        return [
            'resourceBooking_setBookingSubmittedFields' => [
                'resourceBooking_displayBookingDetails',
                'TINYINT(1) DEFAULT 1 NOT NULL',
            ],
            'resourceBooking_bookingSubmittedFields' => [
                'resourceBooking_bookingDetailFields',
                'VARCHAR(1024) DEFAULT '.$this->connection->quote($defaultBookingDetailFields).' NOT NULL',
            ],
        ];
    }
}
