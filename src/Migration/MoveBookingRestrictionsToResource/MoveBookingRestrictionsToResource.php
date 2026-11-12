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

namespace Markocupic\ResourceBookingBundle\Migration\MoveBookingRestrictionsToResource;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\Migration\ResourceMigrationTrait;

/**
 * The booking restrictions used to be module settings:
 *
 *   tl_module.resourceBooking_hideDays -> tl_resource_booking_resource.restrictWeekdays
 *   tl_module.resourceBooking_hideDaysSelection -> tl_resource_booking_resource.allowedWeekdays
 *   tl_module.resourceBooking_addDateStop -> tl_resource_booking_resource.addBookableUntil
 *   tl_module.resourceBooking_dateStop -> tl_resource_booking_resource.bookableUntil
 *
 * The settings of a module are copied to all resources of the resource types
 * selected in that module. If a resource is used in several modules with
 * different settings, the most restrictive setting wins:
 *
 *   - Weekdays: intersection of the allowed weekdays (falls back to the union if the intersection is empty)
 *   - Date: the earliest "bookable until" date
 *
 * Resources whose new fields have already been set are not touched.
 * After the migration, the old flags in tl_module are reset, so the migration runs only once.
 */
class MoveBookingRestrictionsToResource extends AbstractMigration
{
    use ResourceMigrationTrait;

    private const NEW_COLUMNS = [
        'restrictweekdays' => 'ADD restrictWeekdays TINYINT(1) DEFAULT 0 NOT NULL',
        'allowedweekdays' => 'ADD allowedWeekdays BLOB DEFAULT NULL',
        'addbookableuntil' => 'ADD addBookableUntil TINYINT(1) DEFAULT 0 NOT NULL',
        'bookableuntil' => "ADD bookableUntil VARCHAR(11) DEFAULT '' NOT NULL",
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Move weekday and date restrictions from tl_module to tl_resource_booking_resource';
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

        $conditions = $this->getOldFlagConditions();

        if (empty($conditions)) {
            return false;
        }

        $result = $this->connection->fetchOne(
            'SELECT id FROM tl_module WHERE type = ? AND ('.implode(' OR ', $conditions).')',
            [ResourceBookingWeekcalendarController::TYPE],
        );

        return false !== $result;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->addMissingResourceColumns(self::NEW_COLUMNS);

        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_module');
        $hasWeekdayColumns = isset($columns['resourcebooking_hidedays'], $columns['resourcebooking_hidedaysselection']);
        $hasDateColumns = isset($columns['resourcebooking_adddatestop'], $columns['resourcebooking_datestop']);

        $modules = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_module WHERE type = ?',
            [ResourceBookingWeekcalendarController::TYPE],
        );

        // Collect the settings of all modules for each resource.
        // A null value means "no restriction" in that module.
        $weekdaySettings = [];
        $dateSettings = [];

        foreach ($modules as $module) {
            $resourceIds = $this->getResourceIds($module['resourceBooking_resourceTypes'] ?? null);

            $weekdays = null;

            if ($hasWeekdayColumns && !empty($module['resourceBooking_hideDays'])) {
                $weekdays = array_values(array_intersect(RbbConfig::RBB_WEEKDAYS, StringUtil::deserialize($module['resourceBooking_hideDaysSelection'], true)));
            }

            $dateStop = null;

            if ($hasDateColumns && !empty($module['resourceBooking_addDateStop']) && is_numeric($module['resourceBooking_dateStop'])) {
                $dateStop = (int) $module['resourceBooking_dateStop'];
            }

            foreach ($resourceIds as $resourceId) {
                $weekdaySettings[$resourceId][] = $weekdays;
                $dateSettings[$resourceId][] = $dateStop;
            }
        }

        $updated = [];
        $conflicts = [];

        foreach (array_keys($weekdaySettings) as $resourceId) {
            $resource = $this->connection->fetchAssociative('SELECT * FROM tl_resource_booking_resource WHERE id = ?', [$resourceId]);

            if (false === $resource) {
                continue;
            }

            $set = [];

            // Weekdays
            $restrictions = array_values(array_filter($weekdaySettings[$resourceId], static fn ($v) => null !== $v));

            if (!empty($restrictions) && empty($resource['restrictWeekdays'])) {
                $allowed = array_values(array_intersect(RbbConfig::RBB_WEEKDAYS, ...$restrictions));

                if (empty($allowed)) {
                    $allowed = array_values(array_intersect(RbbConfig::RBB_WEEKDAYS, array_merge(...$restrictions)));
                }

                $set['restrictWeekdays'] = 1;
                $set['allowedWeekdays'] = serialize($allowed);

                if ($this->valuesDiffer($weekdaySettings[$resourceId])) {
                    $conflicts[$resourceId] = $resourceId;
                }
            }

            // Bookable until
            $dates = array_filter($dateSettings[$resourceId], static fn ($v) => null !== $v);

            if (!empty($dates) && empty($resource['addBookableUntil'])) {
                $set['addBookableUntil'] = 1;
                $set['bookableUntil'] = (string) min($dates);

                if ($this->valuesDiffer($dateSettings[$resourceId])) {
                    $conflicts[$resourceId] = $resourceId;
                }
            }

            if (!empty($set)) {
                $this->connection->update('tl_resource_booking_resource', $set, ['id' => $resourceId]);
                $updated[] = $resourceId;
            }
        }

        // Reset the old flags so the migration will not run again.
        $reset = [];

        if ($hasWeekdayColumns) {
            $reset['resourceBooking_hideDays'] = 0;
        }

        if ($hasDateColumns) {
            $reset['resourceBooking_addDateStop'] = 0;
        }

        if (!empty($reset)) {
            $this->connection->update('tl_module', $reset, ['type' => ResourceBookingWeekcalendarController::TYPE]);
        }

        $message = \sprintf(
            'Moved the weekday and date restrictions from tl_module to tl_resource_booking_resource (updated resources: %s).',
            empty($updated) ? 'none' : implode(', ', $updated),
        );

        if (!empty($conflicts)) {
            $message .= \sprintf(
                ' The following resources were used in several modules with different settings. The most restrictive setting was applied, please check: %s.',
                implode(', ', $conflicts),
            );
        }

        return $this->createResult(true, $message);
    }

    /**
     * Returns the SQL conditions to detect modules with an active restriction flag,
     * depending on which of the old columns still exist.
     *
     * @return array<string>
     *
     * @throws Exception
     */
    private function getOldFlagConditions(): array
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_module');
        $conditions = [];

        if (isset($columns['resourcebooking_hidedays'])) {
            $conditions[] = 'resourceBooking_hideDays = 1';
        }

        if (isset($columns['resourcebooking_adddatestop'])) {
            $conditions[] = 'resourceBooking_addDateStop = 1';
        }

        return $conditions;
    }
}
