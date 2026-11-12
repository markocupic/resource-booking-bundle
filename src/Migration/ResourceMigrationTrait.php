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

use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Shared helpers for the migrations that move module settings to the resources.
 *
 * @property Connection $connection
 */
trait ResourceMigrationTrait
{
    /**
     * Adds the given columns to tl_resource_booking_resource if they do not exist yet.
     * This way the data can be migrated before the schema update has added the columns.
     *
     * @param array<string, string> $columns lowercase column name => "ADD ..." statement
     *
     * @throws Exception
     */
    private function addMissingResourceColumns(array $columns): void
    {
        $existing = $this->connection->createSchemaManager()->listTableColumns('tl_resource_booking_resource');
        $statements = [];

        foreach ($columns as $name => $statement) {
            if (!isset($existing[$name])) {
                $statements[] = $statement;
            }
        }

        if (!empty($statements)) {
            $this->connection->executeStatement('ALTER TABLE tl_resource_booking_resource '.implode(', ', $statements));
        }
    }

    /**
     * Returns the ids of all resources belonging to the given (serialized) resource type ids.
     *
     * @return array<int>
     *
     * @throws Exception
     */
    private function getResourceIds(string|null $serializedResourceTypeIds): array
    {
        $resourceTypeIds = array_map('intval', StringUtil::deserialize($serializedResourceTypeIds, true));

        if (empty($resourceTypeIds)) {
            return [];
        }

        return array_map(
            'intval',
            $this->connection->fetchFirstColumn(
                'SELECT id FROM tl_resource_booking_resource WHERE pid IN (?)',
                [$resourceTypeIds],
                [ArrayParameterType::INTEGER],
            ),
        );
    }

    /**
     * Returns true if the values collected from several modules differ.
     *
     * @param array<mixed> $values
     */
    private function valuesDiffer(array $values): bool
    {
        return \count(array_unique(array_map('serialize', $values))) > 1;
    }
}
