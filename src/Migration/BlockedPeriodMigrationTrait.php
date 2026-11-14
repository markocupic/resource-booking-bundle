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

namespace Markocupic\ResourceBookingBundle\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Shared helpers for the migrations that write blocked periods.
 *
 * Migrations run before the schema update, so the table and the column
 * "blockedTimes" are created here if they do not exist yet. The schema
 * update adjusts the details afterwards.
 *
 * @property Connection $connection
 */
trait BlockedPeriodMigrationTrait
{
    /**
     * @throws Exception
     */
    private function ensureBlockedPeriodTable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_resource_booking_blocked_period'])) {
            $this->connection->executeStatement(
                "CREATE TABLE tl_resource_booking_blocked_period (
                    id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                    tstamp INT UNSIGNED DEFAULT 0 NOT NULL,
                    title VARCHAR(255) DEFAULT '' NOT NULL,
                    description MEDIUMTEXT DEFAULT NULL,
                    dateFrom INT UNSIGNED DEFAULT 0 NOT NULL,
                    dateTo INT UNSIGNED DEFAULT 0 NOT NULL,
                    blockedTimes BLOB DEFAULT NULL,
                    scope VARCHAR(16) DEFAULT 'all' NOT NULL,
                    resourceTypes BLOB DEFAULT NULL,
                    resources BLOB DEFAULT NULL,
                    published TINYINT(1) DEFAULT 0 NOT NULL,
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            );

            return;
        }

        if (!isset($schemaManager->listTableColumns('tl_resource_booking_blocked_period')['blockedtimes'])) {
            $this->connection->executeStatement('ALTER TABLE tl_resource_booking_blocked_period ADD blockedTimes BLOB DEFAULT NULL');
        }
    }
}
