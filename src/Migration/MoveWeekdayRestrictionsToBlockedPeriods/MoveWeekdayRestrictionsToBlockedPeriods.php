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

namespace Markocupic\ResourceBookingBundle\Migration\MoveWeekdayRestrictionsToBlockedPeriods;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Migration\BlockedPeriodMigrationTrait;

/**
 * The bookable weekdays of a resource (tl_resource_booking_resource.restrictWeekdays/
 * allowedWeekdays) have been replaced by blocked periods with blocked times.
 *
 * For every set of non-bookable weekdays, an unlimited blocked period is created
 * (starting today) that applies to the resources with these weekdays and blocks
 * the non-bookable weekdays for the whole day. Afterwards the old flag is reset.
 * The old columns are then suggested for deletion by the schema update.
 *
 * The weekday settings of the front end modules (tl_module.resourceBooking_hideDays)
 * are first moved to the resources by MoveBookingRestrictionsToResource and then
 * converted by this migration.
 */
class MoveWeekdayRestrictionsToBlockedPeriods extends AbstractMigration
{
    use BlockedPeriodMigrationTrait;

    /**
     * The title of the created blocked periods (can be changed in the back end).
     */
    private const GERMAN_WEEKDAYS = [
        'monday' => 'Montag',
        'tuesday' => 'Dienstag',
        'wednesday' => 'Mittwoch',
        'thursday' => 'Donnerstag',
        'friday' => 'Freitag',
        'saturday' => 'Samstag',
        'sunday' => 'Sonntag',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Convert the bookable weekdays of the resources into blocked periods';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_resource_booking_resource'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_resource_booking_resource');

        if (!isset($columns['restrictweekdays'], $columns['allowedweekdays'])) {
            return false;
        }

        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_resource_booking_resource WHERE restrictWeekdays = 1') > 0;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->ensureBlockedPeriodTable();

        $resources = $this->connection->fetchAllAssociative('SELECT id, title, allowedWeekdays FROM tl_resource_booking_resource WHERE restrictWeekdays = 1');

        // Group the resources by their non-bookable weekdays
        $groups = [];

        foreach ($resources as $resource) {
            $allowed = array_map('strtolower', StringUtil::deserialize($resource['allowedWeekdays'], true));
            $blocked = array_values(array_diff(self::weekdaysMondayFirst(), $allowed));

            if (empty($blocked)) {
                continue;
            }

            $groups[implode(',', $blocked)][] = (int) $resource['id'];
        }

        $created = [];

        foreach ($groups as $weekdays => $resourceIds) {
            $weekdays = explode(',', $weekdays);

            $this->connection->insert('tl_resource_booking_blocked_period', [
                'tstamp' => time(),
                'title' => 'Nicht buchbare Wochentage: '.implode(', ', array_map(static fn (string $weekday): string => self::GERMAN_WEEKDAYS[$weekday], $weekdays)),
                // Displayed in the blocked time slots of the week calendar
                'description' => 'Nicht buchbar',
                'dateFrom' => strtotime('today'),
                'dateTo' => 0,
                'blockedTimes' => serialize(array_map(static fn (string $weekday): array => ['weekday' => $weekday, 'timeFrom' => '', 'timeTo' => ''], $weekdays)),
                'scope' => 'resources',
                'resourceTypes' => null,
                'resources' => serialize(array_map('strval', $resourceIds)),
                'published' => 1,
            ]);

            $created[] = \sprintf('ID %s (resources %s)', $this->connection->lastInsertId(), implode(', ', $resourceIds));
        }

        // Reset the old flag, so the migration will not run again
        $this->connection->executeStatement('UPDATE tl_resource_booking_resource SET restrictWeekdays = 0 WHERE restrictWeekdays = 1');

        return $this->createResult(
            true,
            empty($created)
                ? 'No blocked periods had to be created for the bookable weekdays of the resources.'
                : 'Converted the bookable weekdays of the resources into unlimited blocked periods: '.implode('; ', $created).'. Please check them in the back end (module "Blocked periods").',
        );
    }

    /**
     * @return array<int, string>
     */
    private static function weekdaysMondayFirst(): array
    {
        $weekdays = RbbConfig::RBB_WEEKDAYS;

        // RBB_WEEKDAYS starts with sunday
        return [...\array_slice($weekdays, 1), $weekdays[0]];
    }
}
