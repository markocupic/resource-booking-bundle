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
use Contao\ModuleModel;
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

        $cron = new PurgePastBookingsCron($connection, $this->mockContaoFramework(), false, [], null);
        $cron();
    }

    public function testInvokeDeletesExpiredBookingsAndLogsTheResult(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with($this->stringContains('2 old entries'))
        ;

        $cron = $this->getPartialCron($logger);
        $cron
            ->method('getModuleIdsWithBookings')
            ->willReturn([5, 6])
        ;

        $cron
            ->method('resolveAppConfigForModule')
            ->willReturnMap([
                [5, ['intBackWeeks' => -2]],
                [6, null], // not resolvable -> skipped
            ])
        ;

        $cron
            ->method('calculatePurgeLimit')
            ->with(['intBackWeeks' => -2])
            ->willReturn(1000)
        ;

        $cron
            ->expects($this->once())
            ->method('deleteExpiredBookings')
            ->with(5, 1000)
            ->willReturn(2)
        ;

        $cron();
    }

    public function testInvokeSkipsModulesWithoutAPurgeLimitAndDoesNotLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('info')
        ;

        $cron = $this->getPartialCron($logger);
        $cron
            ->method('getModuleIdsWithBookings')
            ->willReturn([5])
        ;

        $cron
            ->method('resolveAppConfigForModule')
            ->willReturn(['intBackWeeks' => 4])
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

    public function testGetModuleIdsWithBookingsReturnsDistinctPositiveIds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with('SELECT moduleId FROM tl_resource_booking GROUP BY moduleId')
            ->willReturn([
                ['moduleId' => 3],
                ['moduleId' => 0], // filtered out
                ['moduleId' => '5'], // cast to int
                ['moduleId' => null], // filtered out
            ])
        ;

        $cron = new PurgePastBookingsCron($connection, $this->mockContaoFramework(), true, [], null);

        $this->assertSame([3, 5], $this->invoke($cron, 'getModuleIdsWithBookings'));
    }

    public function testResolveAppConfigForModuleReturnsTheMatchingConfig(): void
    {
        $module = $this->mockClassWithProperties(ModuleModel::class, ['resourceBooking_appConfig' => 'default']);

        $adapter = $this->mockAdapter(['findById']);
        $adapter
            ->method('findById')
            ->willReturn($module)
        ;

        $framework = $this->mockContaoFramework([ModuleModel::class => $adapter]);

        $cron = new PurgePastBookingsCron(
            $this->createMock(Connection::class),
            $framework,
            true,
            ['default' => ['intBackWeeks' => -2, 'beginnWeek' => 'monday']],
            null,
        );

        $this->assertSame(
            ['intBackWeeks' => -2, 'beginnWeek' => 'monday'],
            $this->invoke($cron, 'resolveAppConfigForModule', [5]),
        );
    }

    public function testResolveAppConfigForModuleReturnsNullWhenModuleIsMissing(): void
    {
        $adapter = $this->mockAdapter(['findById']);
        $adapter
            ->method('findById')
            ->willReturn(null)
        ;

        $cron = new PurgePastBookingsCron(
            $this->createMock(Connection::class),
            $this->mockContaoFramework([ModuleModel::class => $adapter]),
            true,
            ['default' => ['intBackWeeks' => -2]],
            null,
        );

        $this->assertNull($this->invoke($cron, 'resolveAppConfigForModule', [5]));
    }

    public function testResolveAppConfigForModuleReturnsNullWhenConfigKeyIsUnknown(): void
    {
        $module = $this->mockClassWithProperties(ModuleModel::class, ['resourceBooking_appConfig' => 'unknown']);

        $adapter = $this->mockAdapter(['findById']);
        $adapter
            ->method('findById')
            ->willReturn($module)
        ;

        $cron = new PurgePastBookingsCron(
            $this->createMock(Connection::class),
            $this->mockContaoFramework([ModuleModel::class => $adapter]),
            true,
            ['default' => ['intBackWeeks' => -2]],
            null,
        );

        $this->assertNull($this->invoke($cron, 'resolveAppConfigForModule', [5]));
    }

    public function testCalculatePurgeLimitReturnsNullForNonNegativeBackWeeks(): void
    {
        $cron = new PurgePastBookingsCron(
            $this->createMock(Connection::class),
            $this->mockContaoFramework(),
            true,
            [],
            null,
        );

        $this->assertNull($this->invoke($cron, 'calculatePurgeLimit', [['intBackWeeks' => 0]]));
        $this->assertNull($this->invoke($cron, 'calculatePurgeLimit', [['intBackWeeks' => 3]]));
    }

    public function testCalculatePurgeLimitComputesTheCutoffForNegativeBackWeeks(): void
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
            [],
            null,
        );

        $expected = strtotime('01-01-2020 -2 weeks');

        $this->assertSame(
            $expected,
            $this->invoke($cron, 'calculatePurgeLimit', [['intBackWeeks' => -2, 'beginnWeek' => 'monday']]),
        );
    }

    public function testDeleteExpiredBookingsIssuesTheDeleteStatement(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM tl_resource_booking WHERE moduleId = ? AND endTime < ?',
                [5, 1000],
                $this->anything(),
            )
            ->willReturn(4)
        ;

        $cron = new PurgePastBookingsCron($connection, $this->mockContaoFramework(), true, [], null);

        $this->assertSame(4, $this->invoke($cron, 'deleteExpiredBookings', [5, 1000]));
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
                [],
                $logger,
            ])
            ->onlyMethods([
                'getModuleIdsWithBookings',
                'resolveAppConfigForModule',
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
