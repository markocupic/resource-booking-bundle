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

namespace Markocupic\ResourceBookingBundle\Migration\EnsureItemsAvailable;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Bookings are only accepted if the number of requested items is between 1 and
 * tl_resource_booking_resource.itemsAvailable. Resources without a valid number
 * of available items (NULL, empty or 0, e.g. from very old versions) would no
 * longer be bookable, so they are set to 1 (the default value of the field).
 */
class EnsureItemsAvailable extends AbstractMigration
{
    private const TABLE = 'tl_resource_booking_resource';

    private const CONDITION = 'itemsAvailable IS NULL OR itemsAvailable < 1';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Set the number of available items of resources without a valid value to 1';
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

        // A missing column is added by the schema update with the default value 1
        if (!isset($schemaManager->listTableColumns(self::TABLE)['itemsavailable'])) {
            return false;
        }

        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE %s', self::TABLE, self::CONDITION)) > 0;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $ids = $this->connection->fetchFirstColumn(\sprintf('SELECT id FROM %s WHERE %s', self::TABLE, self::CONDITION));

        $this->connection->executeStatement(\sprintf('UPDATE %s SET itemsAvailable = 1 WHERE %s', self::TABLE, self::CONDITION));

        return $this->createResult(
            true,
            \sprintf('Set the number of available items to 1 for the resource(s) with ID %s. Please check them in the back end.', implode(', ', $ids)),
        );
    }
}
