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

namespace Markocupic\ResourceBookingBundle\Migration\AddBookingUuid;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\Database;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class AddBookingUuid extends AbstractMigration
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        // If the database table itself does not exist we should do nothing
        if (!$schemaManager->tablesExist(['tl_resource_booking'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_resource_booking');

        if (isset($columns['bookinguuid'])) {
            $result = $this->connection->fetchOne('SELECT * FROM tl_resource_booking WHERE bookingUuid = ?', ['']);

            if ($result) {
                // Add booking uuid
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
        // Add booking uuid if there is none
        $this->addBookingUuid();

        return new MigrationResult(
            true,
            'Added booking uuids to tl_resource_booking during the database update process.'
        );
    }

    /**
     * Add booking uuid if there is none.
     *
     * @throws Exception
     */
    private function addBookingUuid(): void
    {
        $this->framework->initialize();
        
        $result = $this->connection->executeQuery('SELECT id FROM tl_resource_booking WHERE bookingUuid = ?', ['']);

        while (false !== ($id = $result->fetchOne())) {
            $set = [
                'bookingUuid' => StringUtil::binToUuid(Database::getInstance()->getUuid()),
            ];

            $this->connection->update('tl_resource_booking', $set, ['id' => $id]);
        }
    }
}
