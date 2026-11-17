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

namespace Markocupic\ResourceBookingBundle\Migration\ConvertBlockedSlots;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Migration\LegacyBlockedSlotConverter;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;

/**
 * The first development state of the mode "slots" stored the start and end time of
 * each blocked slot. Version 6 stores the time slot and the date only, the times
 * are read from the time slot (see BlockedSlots).
 */
class ConvertBlockedSlots extends AbstractMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyBlockedSlotConverter $converter,
    ) {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Store blocked slots as time slot and date';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_resource_booking_blocked_period'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_resource_booking_blocked_period');

        if (!isset($columns['mode'], $columns['resource'], $columns['blockedslots'])) {
            return false;
        }

        return false !== $this->connection->fetchOne(
            "SELECT id FROM tl_resource_booking_blocked_period WHERE mode = ? AND blockedSlots LIKE '%startTime%' LIMIT 1",
            [ResourceBookingBlockedPeriodModel::MODE_SLOTS],
        );
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, resource, dateFrom, blockedSlots FROM tl_resource_booking_blocked_period WHERE mode = ? AND blockedSlots LIKE '%startTime%'",
            [ResourceBookingBlockedPeriodModel::MODE_SLOTS],
        );

        foreach ($rows as $row) {
            $slots = $this->converter->convert((int) $row['resource'], StringUtil::deserialize($row['blockedSlots'], true));
            $dateRange = BlockedSlots::getDateRange($slots) ?? [(int) $row['dateFrom'], (int) $row['dateFrom']];

            $this->connection->update(
                'tl_resource_booking_blocked_period',
                ['blockedSlots' => serialize($slots), 'dateFrom' => $dateRange[0], 'dateTo' => $dateRange[1]],
                ['id' => (int) $row['id']],
            );
        }

        return $this->createResult(true, \sprintf('Converted the blocked slots of %d blocked periods.', \count($rows)));
    }
}
