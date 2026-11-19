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

namespace Markocupic\ResourceBookingBundle\Restriction;

namespace Markocupic\ResourceBookingBundle\Tests\Functional;

use Markocupic\ResourceBookingBundle\Config\RbbConfig;

class DcaSchemaTest extends FunctionalTestCase
{
    public function testCreatesTheTablesOfTheBundle(): void
    {
        $tables = $this->connection->createSchemaManager()->listTableNames();
        sort($tables);

        $this->assertSame(DcaSchema::TABLES, $tables);
    }

    public function testMissingColumnsGetTheDefaultsOfTheDca(): void
    {
        $id = $this->insertRow('tl_resource_booking_resource', ['title' => 'Room']);
        $row = $this->connection->fetchAssociative('SELECT * FROM tl_resource_booking_resource WHERE id = ?', [$id]);

        $this->assertSame(1, $id);
        $this->assertSame('Room', $row['title']);
        $this->assertNull($row['description']);
        $this->assertSame(0, (int) $row['timeSlotType']);
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_BACK, (int) $row['weeksBack']);
        $this->assertSame(1, (int) $row['itemsAvailable']);
    }
}
