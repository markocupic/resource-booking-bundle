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

namespace Markocupic\ResourceBookingBundle\Migration\MoveAppConfigToResource;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\Migration\ResourceMigrationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The number of weeks a user can navigate back/ahead and the auto confirmation
 * used to be part of the app configuration (markocupic_resource_booking.apps),
 * which was assigned to the module (tl_module.resourceBooking_appConfig).
 * Now they are set per resource:
 *
 *   apps.<name>.intBackWeeks (e.g. -10) -> tl_resource_booking_resource.weeksBack (e.g. 10)
 *   apps.<name>.intAheadWeeks (e.g. 60) -> tl_resource_booking_resource.weeksAhead (e.g. 60)
 *   apps.<name>.autoConfirm (e.g. true) -> tl_resource_booking_resource.autoConfirm (e.g. 1)
 *
 * The values of a module are copied to all resources of the resource types
 * selected in that module. If a resource is used in several modules with
 * different values, the most restrictive value wins: the smallest number of
 * weeks and no auto confirmation.
 *
 * The remaining settings (begin_week, permitted_form_fields) stay in the app
 * configuration, which is still selected in the module.
 *
 * The migration runs as long as the new columns do not exist yet. It adds them
 * itself, so it runs only once. Contao runs the migrations before the schema update.
 */
class MoveAppConfigToResource extends AbstractMigration
{
    use ResourceMigrationTrait;

    /**
     * Values of the "default" app configuration that was shipped with the bundle
     * before version 6. They apply if a module refers to an app configuration
     * that does not exist (anymore), e.g. because the bundle no longer ships it.
     */
    private const LEGACY_INT_BACK_WEEKS = -10;

    private const LEGACY_INT_AHEAD_WEEKS = 60;

    private const LEGACY_AUTO_CONFIRM = true;

    private const NEW_COLUMNS = [
        'weeksback' => 'ADD weeksBack INT UNSIGNED DEFAULT '.RbbConfig::RBB_DEFAULT_WEEKS_BACK.' NOT NULL',
        'weeksahead' => 'ADD weeksAhead INT UNSIGNED DEFAULT '.RbbConfig::RBB_DEFAULT_WEEKS_AHEAD.' NOT NULL',
        'autoconfirm' => 'ADD autoConfirm TINYINT(1) DEFAULT 1 NOT NULL',
    ];

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
        return 'Resource Booking Bundle: Move the number of weeks back/ahead and the auto confirmation from the app configuration to tl_resource_booking_resource';
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

        if (!isset($schemaManager->listTableColumns('tl_module')['resourcebooking_appconfig'])) {
            return false;
        }

        // Run only if at least one of the new columns does not exist yet
        $columns = $schemaManager->listTableColumns('tl_resource_booking_resource');

        foreach (array_keys(self::NEW_COLUMNS) as $column) {
            if (!isset($columns[$column])) {
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
        $this->addMissingResourceColumns(self::NEW_COLUMNS);

        $modules = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_module WHERE type = ?',
            [ResourceBookingWeekcalendarController::TYPE],
        );

        $weeksBackSettings = [];
        $weeksAheadSettings = [];
        $autoConfirmSettings = [];

        foreach ($modules as $module) {
            // An empty value means the "default" app configuration (as in Utils::getAppConfigByName())
            $appConfigName = (string) $module['resourceBooking_appConfig'] ?: 'default';

            // If the app configuration does not exist anymore, the values of the former default app configuration apply.
            $appConfig = $this->appConfigs[$appConfigName] ?? [];

            $weeksBack = max(0, -(int) ($appConfig['intBackWeeks'] ?? self::LEGACY_INT_BACK_WEEKS));
            $weeksAhead = max(0, (int) ($appConfig['intAheadWeeks'] ?? self::LEGACY_INT_AHEAD_WEEKS));
            $autoConfirm = (bool) ($appConfig['autoConfirm'] ?? self::LEGACY_AUTO_CONFIRM);

            foreach ($this->getResourceIds($module['resourceBooking_resourceTypes'] ?? null) as $resourceId) {
                $weeksBackSettings[$resourceId][] = $weeksBack;
                $weeksAheadSettings[$resourceId][] = $weeksAhead;
                $autoConfirmSettings[$resourceId][] = $autoConfirm;
            }
        }

        $updated = [];
        $conflicts = [];

        foreach (array_keys($weeksBackSettings) as $resourceId) {
            $this->connection->update(
                'tl_resource_booking_resource',
                [
                    'weeksBack' => min($weeksBackSettings[$resourceId]),
                    'weeksAhead' => min($weeksAheadSettings[$resourceId]),
                    // Only confirm automatically if all modules did so.
                    'autoConfirm' => \in_array(false, $autoConfirmSettings[$resourceId], true) ? 0 : 1,
                ],
                ['id' => $resourceId],
            );

            $updated[] = $resourceId;

            if ($this->valuesDiffer($weeksBackSettings[$resourceId]) || $this->valuesDiffer($weeksAheadSettings[$resourceId]) || $this->valuesDiffer($autoConfirmSettings[$resourceId])) {
                $conflicts[] = $resourceId;
            }
        }

        $message = \sprintf(
            'Moved the number of weeks back/ahead and the auto confirmation from the app configuration to tl_resource_booking_resource (updated resources: %s).',
            empty($updated) ? 'none' : implode(', ', $updated),
        );

        if (!empty($conflicts)) {
            $message .= \sprintf(
                ' The following resources were used in several modules with different values. The most restrictive value was applied, please check: %s.',
                implode(', ', $conflicts),
            );
        }

        // The former keys are no longer needed after the migration.
        $deprecatedKeys = array_filter(
            $this->appConfigs,
            static fn (array $appConfig): bool => isset($appConfig['intBackWeeks']) || isset($appConfig['intAheadWeeks']) || isset($appConfig['autoConfirm']),
        );

        if (!empty($deprecatedKeys)) {
            $message .= \sprintf(
                ' The keys intBackWeeks, intAheadWeeks and autoConfirm can now be removed from the app configurations: %s.',
                implode(', ', array_keys($deprecatedKeys)),
            );
        }

        return $this->createResult(true, $message);
    }
}
