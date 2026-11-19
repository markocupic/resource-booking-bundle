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

use Contao\CoreBundle\Framework\Adapter;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Base class of the functional tests: every test gets a new SQLite database in
 * memory with the tables of the bundle (see DcaSchema). No database server is needed.
 *
 * The bundle runs on MySQL/MariaDB. SQLite does not cover MySQL specifics such as
 * row locks (SELECT … FOR UPDATE) or strict column types.
 */
abstract class FunctionalTestCase extends ContaoTestCase
{
    protected Connection $connection;

    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The functional tests need the PHP extension pdo_sqlite.');
        }

        // The times of the bookings depend on the time zone (e.g. the first day of the week)
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Zurich');

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        DcaSchema::create($this->connection);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
            date_default_timezone_set($this->timezone);
        }

        parent::tearDown();
    }

    /**
     * Inserts a row and returns its ID. Missing columns get the default of the DCA.
     *
     * @param array<string, mixed> $row
     */
    protected function insertRow(string $table, array $row): int
    {
        $this->connection->insert($table, $row);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<int>
     */
    protected function fetchIds(string $sql, array $params = []): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn($sql, $params));
    }

    /**
     * An adapter that calls the real static methods of the class (e.g. DateHelper).
     *
     * @param class-string $class
     */
    protected function createRealAdapter(string $class): Adapter
    {
        return new Adapter($class);
    }
}
