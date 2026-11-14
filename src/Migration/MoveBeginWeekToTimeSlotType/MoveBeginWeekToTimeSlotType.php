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

namespace Markocupic\ResourceBookingBundle\Migration\MoveBeginWeekToTimeSlotType;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\Migration\ResourceMigrationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The first day of the week used to be part of the app configuration
 * (markocupic_resource_booking.apps.<name>.begin_week), which was assigned to
 * the module (tl_module.resourceBooking_appConfig). Now it is set in the
 * timetable (tl_resource_booking_time_slot_type.beginWeek).
 *
 * The value of a module is copied to the timetables of all resources of the
 * resource types selected in that module. If a timetable is used in several
 * modules with different values, the most frequent value wins and the
 * timetable is listed in the result.
 *
 * The migration runs as long as the new column does not exist yet. It adds it
 * itself, so it runs only once. Contao runs the migrations before the schema update.
 */
class MoveBeginWeekToTimeSlotType extends AbstractMigration
{
    use ResourceMigrationTrait;

    /**
     * @param array<string, array<string, mixed>> $appConfigs
     */
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%markocupic_resource_booking.apps%')]
        private readonly array $appConfigs = [],
    ) {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Move the first day of the week from the app configuration to tl_resource_booking_time_slot_type';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_module', 'tl_resource_booking_resource', 'tl_resource_booking_time_slot_type'])) {
            return false;
        }

        // Wait until the module fields have been renamed (RenameModuleFields)
        if (!isset($schemaManager->listTableColumns('tl_module')['resourcebooking_appconfig'])) {
            return false;
        }

        if (!isset($schemaManager->listTableColumns('tl_resource_booking_resource')['timeslottype'])) {
            return false;
        }

        return !isset($schemaManager->listTableColumns('tl_resource_booking_time_slot_type')['beginweek']);
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->connection->executeStatement(
            "ALTER TABLE tl_resource_booking_time_slot_type ADD beginWeek VARCHAR(16) DEFAULT '".RbbConfig::RBB_DEFAULT_BEGIN_WEEK."' NOT NULL",
        );

        $modules = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_module WHERE type = ?',
            [ResourceBookingWeekcalendarController::TYPE],
        );

        /** @var array<int, list<string>> $settings */
        $settings = [];

        foreach ($modules as $module) {
            // An empty value means the "default" app configuration
            $appConfigName = (string) $module['resourceBooking_appConfig'] ?: 'default';
            $beginWeek = $this->getBeginWeek($appConfigName);

            foreach ($this->getTimeSlotTypeIds($this->getResourceIds($module['resourceBooking_resourceTypes'] ?? null)) as $timeSlotTypeId) {
                $settings[$timeSlotTypeId][] = $beginWeek;
            }
        }

        $updated = [];
        $conflicts = [];

        foreach ($settings as $timeSlotTypeId => $values) {
            // The most frequent value wins (on a tie the first one).
            $counts = array_count_values($values);
            arsort($counts);
            $beginWeek = (string) array_key_first($counts);

            if ($beginWeek !== RbbConfig::RBB_DEFAULT_BEGIN_WEEK) {
                $this->connection->update('tl_resource_booking_time_slot_type', ['beginWeek' => $beginWeek], ['id' => $timeSlotTypeId]);
                $updated[] = $timeSlotTypeId.' ('.$beginWeek.')';
            }

            if ($this->valuesDiffer($values)) {
                $conflicts[] = $timeSlotTypeId;
            }
        }

        $message = \sprintf(
            'Added tl_resource_booking_time_slot_type.beginWeek. Timetables that do not start the week on monday: %s.',
            empty($updated) ? 'none' : implode(', ', $updated),
        );

        if (!empty($conflicts)) {
            $message .= \sprintf(
                ' The following timetables were used in modules with different first days of the week. The most frequent value was applied, please check: %s.',
                implode(', ', $conflicts),
            );
        }

        $deprecatedKeys = array_filter(
            $this->appConfigs,
            static fn (array $appConfig): bool => isset($appConfig['begin_week']) || isset($appConfig['beginnWeek']),
        );

        if (!empty($deprecatedKeys)) {
            $message .= \sprintf(
                ' The key begin_week can now be removed from the app configurations: %s.',
                implode(', ', array_keys($deprecatedKeys)),
            );
        }

        return $this->createResult(true, $message);
    }

    private function getBeginWeek(string $appConfigName): string
    {
        $appConfig = $this->appConfigs[$appConfigName] ?? [];
        $beginWeek = strtolower((string) ($appConfig['begin_week'] ?? $appConfig['beginnWeek'] ?? ''));

        return \in_array($beginWeek, RbbConfig::RBB_WEEKDAYS, true) ? $beginWeek : RbbConfig::RBB_DEFAULT_BEGIN_WEEK;
    }

    /**
     * Returns the ids of the timetables of the given resources.
     *
     * @param array<int> $resourceIds
     *
     * @return array<int>
     *
     * @throws Exception
     */
    private function getTimeSlotTypeIds(array $resourceIds): array
    {
        if (empty($resourceIds)) {
            return [];
        }

        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT timeSlotType FROM tl_resource_booking_resource WHERE id IN (?) AND timeSlotType > 0',
            [$resourceIds],
            [ArrayParameterType::INTEGER],
        );

        return array_map('intval', $ids);
    }
}
