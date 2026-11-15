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

namespace Markocupic\ResourceBookingBundle\Migration\AddBookingSource;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Booking\BookingSource;

/**
 * Up to version 5, bookings could only be made by logged-in members in the week calendar.
 * Existing bookings of a member without a source get the source "week_calendar".
 * Bookings without a member keep an empty source (unknown), e.g. bookings of guests
 * created by an extension; extensions can set their own source.
 */
class AddBookingSource extends AbstractMigration
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Set the source of existing bookings of members';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_resource_booking'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_resource_booking');

        if (!isset($columns['bookingsource'], $columns['member'])) {
            return false;
        }

        return false !== $this->connection->fetchOne("SELECT id FROM tl_resource_booking WHERE bookingSource = '' AND member > 0 LIMIT 1");
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $affectedRows = $this->connection->executeStatement(
            "UPDATE tl_resource_booking SET bookingSource = ? WHERE bookingSource = '' AND member > 0",
            [BookingSource::WEEK_CALENDAR],
        );

        return $this->createResult(true, \sprintf('Set the source "%s" for %d bookings of members.', BookingSource::WEEK_CALENDAR, $affectedRows));
    }
}
