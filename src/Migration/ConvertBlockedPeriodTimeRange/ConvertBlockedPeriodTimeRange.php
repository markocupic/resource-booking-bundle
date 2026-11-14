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

namespace Markocupic\ResourceBookingBundle\Migration\ConvertBlockedPeriodTimeRange;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Migration\BlockedPeriodMigrationTrait;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;

/**
 * A pre-release version of the blocked periods had a single time range for all
 * days (addTimeRange, timeFrom, timeTo). It is converted into the blocked times
 * (row wizard): one row "daily" with the same times.
 */
class ConvertBlockedPeriodTimeRange extends AbstractMigration
{
    use BlockedPeriodMigrationTrait;

    private const TABLE = 'tl_resource_booking_blocked_period';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Convert the time range of the blocked periods into blocked times';
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

        $columns = $schemaManager->listTableColumns(self::TABLE);

        if (!isset($columns['addtimerange'], $columns['timefrom'], $columns['timeto'])) {
            return false;
        }

        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE addTimeRange = 1', self::TABLE)) > 0;
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->ensureBlockedPeriodTable();

        $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT id, timeFrom, timeTo FROM %s WHERE addTimeRange = 1', self::TABLE));

        foreach ($rows as $row) {
            $this->connection->update(
                self::TABLE,
                [
                    'blockedTimes' => serialize([[
                        'weekday' => 'daily',
                        'timeFrom' => self::formatTime(ResourceBookingRestriction::timeToSeconds($row['timeFrom'])),
                        'timeTo' => self::formatTime(ResourceBookingRestriction::timeToSeconds($row['timeTo'])),
                    ]]),
                    'addTimeRange' => 0,
                ],
                ['id' => (int) $row['id']],
            );
        }

        return $this->createResult(true, \sprintf('Converted the time range of %d blocked period(s) into blocked times.', \count($rows)));
    }

    private static function formatTime(int|null $seconds): string
    {
        if (null === $seconds) {
            return '';
        }

        return 86400 === $seconds ? '24:00' : gmdate('H:i', $seconds);
    }
}
