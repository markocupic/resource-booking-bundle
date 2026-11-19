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

namespace Markocupic\ResourceBookingBundle\Migration\MoveNotificationsToResource;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Util\CsvList;

/**
 * Up to version 4 the notifications were selected in the frontend module.
 * Now they are selected per resource:
 *
 *   tl_module.rbbOnBookingNotification -> tl_resource_booking_resource.bookingNotification
 *   tl_module.rbbOnCancelingNotification -> tl_resource_booking_resource.cancellationNotification
 *
 * The notifications of a module are copied to all resources of the resource
 * types selected in that module. If a resource is used in several modules with
 * different notifications, the notification of the module with the lowest ID
 * wins and the resource is listed in the migration result. Resources that
 * already have a notification are not touched.
 *
 * After the migration, the old fields in tl_module are reset, so the migration runs only once.
 */
class MoveNotificationsToResource extends AbstractMigration
{
    private const MODULE_TYPE = 'resourceBookingWeekcalendar';

    /**
     * Old column in tl_module => new column in tl_resource_booking_resource.
     */
    private const FIELDS = [
        'rbbOnBookingNotification' => 'bookingNotification',
        'rbbOnCancelingNotification' => 'cancellationNotification',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Move the notifications from tl_module to tl_resource_booking_resource';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_module', 'tl_resource_booking_resource'])) {
            return false;
        }

        $conditions = $this->getOldFieldConditions();

        if (empty($conditions)) {
            return false;
        }

        $result = $this->connection->fetchOne(
            'SELECT id FROM tl_module WHERE type = ? AND ('.implode(' OR ', $conditions).')',
            [self::MODULE_TYPE],
        );

        return false !== $result;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->addMissingColumns();

        $moduleColumns = $this->connection->createSchemaManager()->listTableColumns('tl_module');
        $oldFields = array_filter(array_keys(self::FIELDS), static fn (string $field): bool => isset($moduleColumns[strtolower($field)]));

        $modules = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_module WHERE type = ? ORDER BY id',
            [self::MODULE_TYPE],
        );

        // New column => resource id => list of notification ids (one entry per module, 0 = none)
        $settings = [];

        foreach ($modules as $module) {
            foreach ($this->getResourceIds($module['resourceBooking_resourceTypes'] ?? null) as $resourceId) {
                foreach ($oldFields as $oldField) {
                    $settings[self::FIELDS[$oldField]][$resourceId][] = (int) ($module[$oldField] ?? 0);
                }
            }
        }

        $updated = [];
        $conflicts = [];

        foreach ($settings as $newField => $resources) {
            foreach ($resources as $resourceId => $notificationIds) {
                $selected = array_values(array_unique(array_filter($notificationIds)));

                if (empty($selected)) {
                    continue;
                }

                // Do not overwrite a notification that has already been set in the resource.
                if ((int) $this->connection->fetchOne("SELECT $newField FROM tl_resource_booking_resource WHERE id = ?", [$resourceId]) > 0) {
                    continue;
                }

                $this->connection->update('tl_resource_booking_resource', [$newField => $selected[0]], ['id' => $resourceId]);
                $updated[$resourceId] = $resourceId;

                if (\count(array_unique($notificationIds)) > 1) {
                    $conflicts[$resourceId] = $resourceId;
                }
            }
        }

        // Reset the old fields so the migration will not run again.
        if (!empty($oldFields)) {
            $this->connection->update('tl_module', array_fill_keys($oldFields, 0), ['type' => self::MODULE_TYPE]);
        }

        $message = \sprintf(
            'Moved the notifications from tl_module to tl_resource_booking_resource (updated resources: %s).',
            empty($updated) ? 'none' : implode(', ', $updated),
        );

        if (!empty($conflicts)) {
            $message .= \sprintf(
                ' The following resources were used in several modules with different notifications. The notification of the first module was applied, please check: %s.',
                implode(', ', $conflicts),
            );
        }

        return $this->createResult(true, $message);
    }

    /**
     * @return list<string>
     *
     * @throws Exception
     */
    private function getOldFieldConditions(): array
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_module');
        $conditions = [];

        foreach (array_keys(self::FIELDS) as $oldField) {
            if (isset($columns[strtolower($oldField)])) {
                $conditions[] = $oldField.' > 0';
            }
        }

        return $conditions;
    }

    /**
     * Adds the new columns if the schema update has not added them yet.
     *
     * @throws Exception
     */
    private function addMissingColumns(): void
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_resource_booking_resource');
        $statements = [];

        foreach (self::FIELDS as $newField) {
            if (!isset($columns[strtolower($newField)])) {
                $statements[] = 'ADD '.$newField.' INT UNSIGNED DEFAULT 0 NOT NULL';
            }
        }

        if (!empty($statements)) {
            $this->connection->executeStatement('ALTER TABLE tl_resource_booking_resource '.implode(', ', $statements));
        }
    }

    /**
     * @return list<int>
     *
     * @throws Exception
     */
    private function getResourceIds(string|null $resourceTypeIds): array
    {
        // CSV or (not migrated yet) serialized array
        $resourceTypeIds = CsvList::parseIds($resourceTypeIds);

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
}
