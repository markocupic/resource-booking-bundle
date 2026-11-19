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

namespace Markocupic\ResourceBookingBundle\Tests\Functional\Migration;

use Markocupic\ResourceBookingBundle\Migration\ConvertBlockedPeriodTimeRange\ConvertBlockedPeriodTimeRange;
use Markocupic\ResourceBookingBundle\Tests\Functional\FunctionalTestCase;

class ConvertBlockedPeriodTimeRangeTest extends FunctionalTestCase
{
    public function testDoesNotRunWithoutTheOldColumns(): void
    {
        $this->assertFalse((new ConvertBlockedPeriodTimeRange($this->connection))->shouldRun());
    }

    public function testConvertsTheTimeRangeIntoADailyBlockedTime(): void
    {
        $this->addOldColumns();

        // Contao stores times as timestamp of 1970-01-01 in the local time zone
        $converted = $this->insertRow('tl_resource_booking_blocked_period', [
            'title' => 'Mittag',
            'addTimeRange' => 1,
            'timeFrom' => mktime(12, 0, 0, 1, 1, 1970),
            'timeTo' => mktime(13, 30, 0, 1, 1, 1970),
        ]);
        $untilEndOfDay = $this->insertRow('tl_resource_booking_blocked_period', ['title' => 'Abend', 'addTimeRange' => 1, 'timeFrom' => mktime(18, 0, 0, 1, 1, 1970)]);
        $untouched = $this->insertRow('tl_resource_booking_blocked_period', ['title' => 'Ferien', 'addTimeRange' => 0]);

        $migration = new ConvertBlockedPeriodTimeRange($this->connection);

        $this->assertTrue($migration->shouldRun());
        $this->assertTrue($migration->run()->isSuccessful());
        $this->assertFalse($migration->shouldRun());

        $this->assertSame([['weekday' => 'daily', 'timeFrom' => '12:00', 'timeTo' => '13:30']], $this->getBlockedTimes($converted));
        $this->assertSame([['weekday' => 'daily', 'timeFrom' => '18:00', 'timeTo' => '']], $this->getBlockedTimes($untilEndOfDay));
        $this->assertNull($this->connection->fetchOne('SELECT blockedTimes FROM tl_resource_booking_blocked_period WHERE id = ?', [$untouched]));
    }

    /**
     * The columns of the pre-release version (no longer in the DCA).
     */
    private function addOldColumns(): void
    {
        $this->connection->executeStatement('ALTER TABLE tl_resource_booking_blocked_period ADD addTimeRange INTEGER DEFAULT 0 NOT NULL');
        $this->connection->executeStatement('ALTER TABLE tl_resource_booking_blocked_period ADD timeFrom INTEGER DEFAULT NULL');
        $this->connection->executeStatement('ALTER TABLE tl_resource_booking_blocked_period ADD timeTo INTEGER DEFAULT NULL');
    }

    /**
     * @return array<mixed>
     */
    private function getBlockedTimes(int $id): array
    {
        return unserialize((string) $this->connection->fetchOne('SELECT blockedTimes FROM tl_resource_booking_blocked_period WHERE id = ?', [$id]));
    }
}
