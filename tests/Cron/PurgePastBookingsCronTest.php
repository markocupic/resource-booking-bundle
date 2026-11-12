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

use Contao\Date;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Cron\PurgePastBookingsCron;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

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

        $cron = new PurgePastBookingsCron($connection, $this->mockContaoFramework(), false, ['default' => ['begin_week' => 'monday']], null);
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
            ->method('getWeeksBackByResource')
            ->willReturn([5 => 2, 6 => 0])
        ;

        $cron
            ->method('calculatePurgeLimit')
            ->willReturnMap([
                [2, 1000],
                [0, null], // weeksBack = 0 -> skipped
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
            ->method('getWeeksBackByResource')
            ->willReturn([5 => 0])
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

    public function testGetWeeksBackByResourceReturnsTheValuesIndexedByResourceId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with('SELECT id, weeksBack FROM tl_resource_booking_resource')
            ->willReturn([
                ['id' => 3, 'weeksBack' => 10],
                ['id' => 0, 'weeksBack' => 5], // filtered out
                ['id' => '7', 'weeksBack' => '2'], // cast to int
            ])
        ;

        $cron = new PurgePastBookingsCron($connection, $this->mockContaoFramework(), true, ['default' => ['begin_week' => 'monday']], null);

        $this->assertSame([3 => 10, 7 => 2], $this->invoke($cron, 'getWeeksBackByResource'));
    }

    public function testCalculatePurgeLimitReturnsNullIfTheUserCannotNavigateBack(): void
    {
        $cron = new PurgePastBookingsCron(
            $this->createMock(Connection::class),
            $this->mockContaoFramework(),
            true,
            ['default' => ['begin_week' => 'monday']],
            null,
        );

        $this->assertNull($this->invoke($cron, 'calculatePurgeLimit', [0]));
        $this->assertNull($this->invoke($cron, 'calculatePurgeLimit', [-3]));
    }

    public function testCalculatePurgeLimitComputesTheCutoff(): void
    {
        // Mock Date::parse so the result no longer depends on the current date.
        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturn('01-01-2020')
        ;

        $cron = new PurgePastBookingsCron(
            $this->createMock(Connection::class),
            $this->mockContaoFramework([Date::class => $dateAdapter]),
            true,
            ['default' => ['begin_week' => 'monday']],
            null,
        );

        $expected = strtotime('01-01-2020 -2 weeks');

        $this->assertSame($expected, $this->invoke($cron, 'calculatePurgeLimit', [2]));
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

        $cron = new PurgePastBookingsCron($connection, $this->mockContaoFramework(), true, ['default' => ['begin_week' => 'monday']], null);

        $this->assertSame(4, $this->invoke($cron, 'deleteExpiredBookings', [5, 1000]));
    }

    public function testGetBeginWeekUsesTheDefaultAppOrTheFirstApp(): void
    {
        $connection = $this->createMock(Connection::class);
        $framework = $this->mockContaoFramework();

        $cron = new PurgePastBookingsCron($connection, $framework, true, ['other' => ['begin_week' => 'sunday'], 'default' => ['begin_week' => 'tuesday']], null);
        $this->assertSame('tuesday', $this->invoke($cron, 'getBeginWeek'));

        $cron = new PurgePastBookingsCron($connection, $framework, true, ['other' => ['begin_week' => 'sunday']], null);
        $this->assertSame('sunday', $this->invoke($cron, 'getBeginWeek'));

        $cron = new PurgePastBookingsCron($connection, $framework, true, [], null);
        $this->assertSame('monday', $this->invoke($cron, 'getBeginWeek'));
    }

    /**
     * @return PurgePastBookingsCron&MockObject
     */
    private function getPartialCron(LoggerInterface $logger): PurgePastBookingsCron
    {
        return $this->getMockBuilder(PurgePastBookingsCron::class)
            ->setConstructorArgs([
                $this->createMock(Connection::class),
                $this->mockContaoFramework(),
                true,
                ['default' => ['begin_week' => 'monday']],
                $logger,
            ])
            ->onlyMethods([
                'getWeeksBackByResource',
                'calculatePurgeLimit',
                'deleteExpiredBookings',
            ])
            ->getMock()
        ;
    }

    private function invoke(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }
}
