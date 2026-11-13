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

namespace Markocupic\ResourceBookingBundle\Migration\AddTimeSlotWeekdays;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;

/**
 * Adds the new field tl_resource_booking_time_slot.weekdays and makes all
 * existing time slots available on every weekday, so nothing changes in the
 * week calendar after the update.
 */
class AddTimeSlotWeekdays extends AbstractMigration
{
    private const TABLE = 'tl_resource_booking_time_slot';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Make all time slots available on every weekday (tl_resource_booking_time_slot.weekdays)';
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

        if (!isset($columns['weekdays'])) {
            return true;
        }

        return (int) $this->connection->fetchOne(\sprintf("SELECT COUNT(*) FROM %s WHERE weekdays IS NULL OR weekdays = ''", self::TABLE)) > 0;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns(self::TABLE);

        // Migrations run before the schema update: add the column ourselves.
        if (!isset($columns['weekdays'])) {
            $this->connection->executeStatement(\sprintf('ALTER TABLE %s ADD weekdays BLOB DEFAULT NULL', self::TABLE));
        }

        $count = $this->connection->executeStatement(
            \sprintf("UPDATE %s SET weekdays = ? WHERE weekdays IS NULL OR weekdays = ''", self::TABLE),
            [serialize(RbbConfig::RBB_WEEKDAYS)],
        );

        return $this->createResult(true, \sprintf('Made %d time slot(s) available on every weekday (tl_resource_booking_time_slot.weekdays).', $count));
    }
}
