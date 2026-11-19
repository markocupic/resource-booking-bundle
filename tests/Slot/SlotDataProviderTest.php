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

namespace Markocupic\ResourceBookingBundle\Tests\Slot;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Restriction\BlockedPeriod;
use Markocupic\ResourceBookingBundle\Slot\SlotDataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SlotDataProviderTest extends TestCase
{
    public function testGroupsBookingsByStartAndEndTime(): void
    {
        $rows = [
            ['id' => 1, 'startTime' => 100, 'endTime' => 200],
            ['id' => 2, 'startTime' => 100, 'endTime' => 200],
            ['id' => 3, 'startTime' => 100, 'endTime' => 300],
        ];

        $grouped = SlotDataProvider::groupBookingsBySlot($rows);

        $this->assertSame([1, 2], array_column($grouped['100-200'], 'id'));
        $this->assertSame([3], array_column($grouped['100-300'], 'id'));
    }

    public function testPreloadedBookingsAreFetchedWithOneQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('startTime >= ? AND startTime < ?'), [7, 1000, 2000])
            ->willReturn([
                ['id' => 1, 'pid' => 7, 'startTime' => 1100, 'endTime' => 1200],
                ['id' => 2, 'pid' => 7, 'startTime' => 1300, 'endTime' => 1400],
            ])
        ;

        $provider = $this->createProvider($connection);
        $provider->preloadBookings(7, 1000, 2000);

        // Every slot of the preloaded range is answered without another query.
        $this->assertSame([1], array_column($provider->getBookings(7, 1100, 1200), 'id'));
        $this->assertSame([2], array_column($provider->getBookings(7, 1300, 1400), 'id'));
        $this->assertSame([], $provider->getBookings(7, 1500, 1600));
    }

    public function testBookingsOutsideThePreloadedRangeAreQueried(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnCallback(static fn (string $sql, array $params): array => str_contains($sql, 'startTime = ?')
                ? [['id' => 9, 'pid' => 7, 'startTime' => 5000, 'endTime' => 5100]]
                : [])
        ;

        $provider = $this->createProvider($connection);
        $provider->preloadBookings(7, 1000, 2000);

        $this->assertSame([9], array_column($provider->getBookings(7, 5000, 5100), 'id'));
    }

    public function testClearBookingsForgetsThePreloadedBookings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        $provider = $this->createProvider($connection);
        $provider->preloadBookings(7, 1000, 2000);
        $provider->clearBookings();

        // Not preloaded anymore: queried again.
        $provider->getBookings(7, 1100, 1200);
    }

    public function testBlockedPeriodsAreLoadedOncePerRequest(): void
    {
        $periods = [['id' => 1, 'mode' => 'period']];

        $adapter = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock()
        ;

        $adapter
            ->expects($this->exactly(2))
            ->method('__call')
            ->with('findPublishedRows', [])
            ->willReturn($periods)
        ;

        $framework = $this->createStub(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturnMap([[ResourceBookingBlockedPeriodModel::class, $adapter]])
        ;

        $provider = new SlotDataProvider($framework, $this->createStub(TokenStorageInterface::class), $this->createStub(Connection::class));

        $blockedPeriods = $provider->getBlockedPeriods();

        $this->assertContainsOnlyInstancesOf(BlockedPeriod::class, $blockedPeriods);
        $this->assertSame([1], array_map(static fn (BlockedPeriod $period): int => $period->id, $blockedPeriods));

        // Same objects: not loaded again
        $this->assertSame($blockedPeriods, $provider->getBlockedPeriods());

        // After reset() (next request) they are loaded again: new objects
        $provider->reset();
        $reloaded = $provider->getBlockedPeriods();

        $this->assertNotSame($blockedPeriods, $reloaded);
        $this->assertSame([1], array_map(static fn (BlockedPeriod $period): int => $period->id, $reloaded));
    }

    private function createProvider(Connection $connection): SlotDataProvider
    {
        return new SlotDataProvider(
            $this->createStub(ContaoFramework::class),
            $this->createStub(TokenStorageInterface::class),
            $connection,
        );
    }
}
