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

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Cron\PurgePastBookingsCron;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

class PurgePastBookingsCronTest extends ContaoTestCase
{
    public function testDoesNothingWhenPurgingIsDisabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAllAssociative')
        ;

        $connection
            ->expects($this->never())
            ->method('executeStatement')
        ;

        $cron = new PurgePastBookingsCron($connection, false, null);
        $cron();
    }

    public function testInvokeDeletesExpiredBookingsPerResourceAndLogsTheResult(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('2 old entries'))
        ;

        $cron = $this->getPartialCron($logger);
        $cron
            ->method('getPurgeSettingsByResource')
            ->willReturn([
                5 => ['weeksBack' => 2, 'beginWeek' => 'sunday'],
                6 => ['weeksBack' => 0, 'beginWeek' => 'monday'],
            ])
        ;

        $cron
            ->method('calculatePurgeLimit')
            ->willReturnMap([
                [2, 'sunday', 1000],
                [0, 'monday', null], // weeksBack = 0 -> skipped
            ])
        ;

        $cron
            ->expects($this->once())
            ->method('deleteExpiredBookings')
            ->with(5, 1000)
            ->willReturn(2)
        ;

        $cron();
    }

    public function testInvokeSkipsResourcesWithoutAPurgeLimitAndDoesNotLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('info')
        ;

        $cron = $this->getPartialCron($logger);
        $cron
            ->method('getPurgeSettingsByResource')
            ->willReturn([5 => ['weeksBack' => 0, 'beginWeek' => 'monday']])
        ;

        $cron
            ->method('calculatePurgeLimit')
            ->willReturn(null)
        ;

        $cron
            ->expects($this->never())
            ->method('deleteExpiredBookings')
        ;

        $cron();
    }

    public function testGetPurgeSettingsByResourceReturnsTheValuesIndexedByResourceId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('LEFT JOIN tl_resource_booking_time_slot_type'))
            ->willReturn([
                ['id' => 3, 'weeksBack' => 10, 'beginWeek' => 'sunday'],
                ['id' => 0, 'weeksBack' => 5, 'beginWeek' => 'monday'], // filtered out
                ['id' => '7', 'weeksBack' => '2', 'beginWeek' => null], // no timetable -> monday
                ['id' => 8, 'weeksBack' => 1, 'beginWeek' => 'invalid'], // invalid -> monday
            ])
        ;

        $cron = new PurgePastBookingsCron($connection, true, null);

        $this->assertSame(
            [
                3 => ['weeksBack' => 10, 'beginWeek' => 'sunday'],
                7 => ['weeksBack' => 2, 'beginWeek' => 'monday'],
                8 => ['weeksBack' => 1, 'beginWeek' => 'monday'],
            ],
            $this->invoke($cron, 'getPurgeSettingsByResource'),
        );
    }

    public function testCalculatePurgeLimitReturnsNullIfTheUserCannotNavigateBack(): void
    {
        $cron = new PurgePastBookingsCron($this->createStub(Connection::class), true, null);

        $this->assertNull($this->invoke($cron, 'calculatePurgeLimit', [0, 'monday']));
        $this->assertNull($this->invoke($cron, 'calculatePurgeLimit', [-3, 'monday']));
    }

    public function testCalculatePurgeLimitCountsTheWeeksFromTheFirstDayOfTheWeekOfTheTimetable(): void
    {
        // Wednesday
        $clock = new MockClock(new \DateTimeImmutable('2030-10-16 12:00'));
        $cron = new PurgePastBookingsCron($this->createStub(Connection::class), true, null, $clock);

        // Two weeks before sunday, 2030-10-13
        $this->assertSame(strtotime('2030-09-29 00:00'), $this->invoke($cron, 'calculatePurgeLimit', [2, 'sunday']));

        // Two weeks before monday, 2030-10-14
        $this->assertSame(strtotime('2030-09-30 00:00'), $this->invoke($cron, 'calculatePurgeLimit', [2, 'monday']));
    }

    public function testDeleteExpiredBookingsIssuesTheDeleteStatement(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM tl_resource_booking WHERE pid = ? AND endTime < ?',
                [5, 1000],
                $this->anything(),
            )
            ->willReturn(4)
        ;

        $cron = new PurgePastBookingsCron($connection, true, null);

        $this->assertSame(4, $this->invoke($cron, 'deleteExpiredBookings', [5, 1000]));
    }

    /**
     * @return PurgePastBookingsCron&MockObject
     */
    private function getPartialCron(LoggerInterface $logger): PurgePastBookingsCron
    {
        return $this->getMockBuilder(PurgePastBookingsCron::class)
            ->setConstructorArgs([
                $this->createStub(Connection::class),
                true,
                $logger,
            ])
            ->onlyMethods([
                'getPurgeSettingsByResource',
                'calculatePurgeLimit',
                'deleteExpiredBookings',
            ])
            ->getMock()
        ;
    }

    private function invoke(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invokeArgs($object, $args);
    }
}
