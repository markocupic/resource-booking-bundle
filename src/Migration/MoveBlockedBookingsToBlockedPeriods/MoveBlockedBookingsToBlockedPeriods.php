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

namespace Markocupic\ResourceBookingBundle\Migration\MoveBlockedBookingsToBlockedPeriods;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Migration\LegacyBlockedSlotConverter;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;

/**
 * Up to version 5, single slots were blocked with "blocked bookings"
 * (tl_resource_booking.isBlocked). Version 6 blocks single slots with a blocked
 * period in the mode "slots". Each resource gets one blocked period per title and
 * description of its blocked bookings, the blocked bookings are deleted. A blocked
 * slot is stored as time slot and date (see BlockedSlots); blocked bookings without
 * time slot get the time slot of the timetable with the same times.
 *
 * The migration runs before the database update, therefore it creates the new
 * columns of tl_resource_booking_blocked_period itself.
 */
class MoveBlockedBookingsToBlockedPeriods extends AbstractMigration
{
    public const DEFAULT_TITLE = 'Gesperrte Slots';

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBlockedSlotConverter $converter,
    ) {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Move blocked bookings to blocked periods (mode "slots")';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_resource_booking', 'tl_resource_booking_blocked_period'])) {
            return false;
        }

        if (!isset($schemaManager->listTableColumns('tl_resource_booking')['isblocked'])) {
            return false;
        }

        return false !== $this->connection->fetchOne('SELECT id FROM tl_resource_booking WHERE isBlocked = 1 LIMIT 1');
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $this->addColumns();

        $bookings = $this->connection->fetchAllAssociative(
            'SELECT id, pid, title, description, timeSlotId, startTime, endTime FROM tl_resource_booking WHERE isBlocked = 1 ORDER BY pid, startTime',
        );

        $groups = [];

        foreach ($bookings as $booking) {
            $title = trim((string) $booking['title']);
            $description = trim((string) $booking['description']);
            $key = $booking['pid'].'|'.$title.'|'.$description;

            $groups[$key] ??= [
                'resource' => (int) $booking['pid'],
                'title' => '' !== $title ? $title : self::DEFAULT_TITLE,
                // The note in the week calendar: the description, otherwise the title of the blocked booking
                'description' => '' !== $description ? $description : $title,
                'slots' => [],
            ];

            $groups[$key]['slots'][] = [
                'timeSlot' => (int) $booking['timeSlotId'],
                'startTime' => (int) $booking['startTime'],
                'endTime' => (int) $booking['endTime'],
            ];
        }

        $this->connection->beginTransaction();

        try {
            foreach ($groups as $group) {
                $slots = $this->converter->convert($group['resource'], $group['slots']);
                $dateRange = BlockedSlots::getDateRange($slots);

                if (null === $dateRange) {
                    continue;
                }

                $this->connection->insert('tl_resource_booking_blocked_period', [
                    'tstamp' => time(),
                    'title' => $group['title'],
                    'description' => $group['description'],
                    'dateFrom' => $dateRange[0],
                    'dateTo' => $dateRange[1],
                    'mode' => ResourceBookingBlockedPeriodModel::MODE_SLOTS,
                    'resource' => $group['resource'],
                    'blockedSlots' => serialize($slots),
                    'published' => 1,
                ]);
            }

            $this->connection->executeStatement('DELETE FROM tl_resource_booking WHERE isBlocked = 1');

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $this->createResult(true, \sprintf('Moved %d blocked bookings to %d blocked periods (mode "slots").', \count($bookings), \count($groups)));
    }

    /**
     * Creates the new columns of tl_resource_booking_blocked_period (see the DCA).
     *
     * @throws Exception
     */
    private function addColumns(): void
    {
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_resource_booking_blocked_period');

        if (!isset($columns['mode'])) {
            $this->connection->executeStatement("ALTER TABLE tl_resource_booking_blocked_period ADD mode VARCHAR(16) DEFAULT 'period' NOT NULL");
        }

        if (!isset($columns['resource'])) {
            $this->connection->executeStatement('ALTER TABLE tl_resource_booking_blocked_period ADD resource INT UNSIGNED DEFAULT 0 NOT NULL');
        }

        if (!isset($columns['blockedslots'])) {
            $this->connection->executeStatement('ALTER TABLE tl_resource_booking_blocked_period ADD blockedSlots MEDIUMBLOB DEFAULT NULL');
        }
    }
}
