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

namespace Markocupic\ResourceBookingBundle\Tests\Cron;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Cron\MarkBookingsAsExpiredCron;
use PHPUnit\Framework\TestCase;

class MarkBookingsAsExpiredCronTest extends TestCase
{
    public function testMarksPastBookingsAsExpired(): void
    {
        $captured = null;

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$captured): int {
                    $captured = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return 3;
                },
            )
        ;

        $before = time();
        (new MarkBookingsAsExpiredCron($connection))();
        $after = time();

        $this->assertSame(
            'UPDATE tl_resource_booking SET upcoming = 0 WHERE endTime < ? AND upcoming = ?',
            $captured['sql'],
        );

        // First parameter is the "now" timestamp used as the cut-off.
        $this->assertIsInt($captured['params'][0]);
        $this->assertGreaterThanOrEqual($before, $captured['params'][0]);
        $this->assertLessThanOrEqual($after, $captured['params'][0]);

        // Second parameter targets the currently "upcoming" bookings.
        $this->assertSame(1, $captured['params'][1]);

        $this->assertSame([Types::INTEGER, Types::BOOLEAN], $captured['types']);
    }
}
