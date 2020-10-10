<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Migration\AddBookingUuid;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\Database;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Class AddBookingUuid.
 */
class AddBookingUuid extends AbstractMigration
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * AddBookingUuid constructor.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->getSchemaManager();

        // If the database table itself does not exist we should do nothing
        if (!$schemaManager->tablesExist(['tl_resource_booking'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_resource_booking');

        if (isset($columns['bookinguuid'])) {
            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_resource_booking WHERE bookingUuid=?')->execute('');

            if ($objDb->numRows) {
                // Add booking uuid
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        // Add booking uuid if there is none
        $this->addBookingUuid();

        return new MigrationResult(
            true,
            'Added booking uuids to tl_resource_booking during the database update process.'
        );
    }

    /**
     * Add booking uuid if there is none.
     */
    private function addBookingUuid(): void
    {
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_resource_booking WHERE bookingUuid=?')->execute('');

        while ($objDb->next()) {
            $set = [
                'bookingUuid' => StringUtil::binToUuid(Database::getInstance()->getUuid()),
            ];
            Database::getInstance()->prepare('UPDATE tl_resource_booking %s WHERE id=?')->set($set)->execute($objDb->id);
        }
    }
}
