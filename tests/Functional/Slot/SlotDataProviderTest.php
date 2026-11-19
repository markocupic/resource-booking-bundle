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

namespace Markocupic\ResourceBookingBundle\Tests\Functional\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\Slot\SlotDataProvider;
use Markocupic\ResourceBookingBundle\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SlotDataProviderTest extends FunctionalTestCase
{
    public function testPreloadedBookingsOfTheWeek(): void
    {
        $monday = strtotime('2030-10-14');
        $slot1 = [strtotime('2030-10-14 08:00'), strtotime('2030-10-14 09:00')];
        $slot2 = [strtotime('2030-10-15 08:00'), strtotime('2030-10-15 09:00')];

        $a = $this->insertBooking(7, ...$slot1);
        $b = $this->insertBooking(7, ...$slot1);
        $c = $this->insertBooking(7, ...$slot2);
        // Another resource and the next week
        $this->insertBooking(8, ...$slot1);
        $nextWeek = $this->insertBooking(7, strtotime('2030-10-21 08:00'), strtotime('2030-10-21 09:00'));

        $provider = $this->createProvider();
        $provider->preloadBookings(7, $monday, strtotime('+7 days', $monday));

        $this->assertSame([$a, $b], array_map('intval', array_column($provider->getBookings(7, ...$slot1), 'id')));
        $this->assertSame([$c], array_map('intval', array_column($provider->getBookings(7, ...$slot2), 'id')));
        $this->assertSame([], $provider->getBookings(7, strtotime('2030-10-16 08:00'), strtotime('2030-10-16 09:00')));

        // Outside the preloaded week: queried directly
        $this->assertSame([$nextWeek], array_map('intval', array_column($provider->getBookings(7, strtotime('2030-10-21 08:00'), strtotime('2030-10-21 09:00')), 'id')));
    }

    public function testNewBookingsAreFoundAfterClearingThePreloadedBookings(): void
    {
        $monday = strtotime('2030-10-14');
        $slot = [strtotime('2030-10-14 08:00'), strtotime('2030-10-14 09:00')];

        $provider = $this->createProvider();
        $provider->preloadBookings(7, $monday, strtotime('+7 days', $monday));

        $booking = $this->insertBooking(7, ...$slot);

        // Still the preloaded state
        $this->assertSame([], $provider->getBookings(7, ...$slot));

        $provider->clearBookings();

        $this->assertSame([$booking], array_map('intval', array_column($provider->getBookings(7, ...$slot), 'id')));
    }

    public function testBookingsWithOtherTimesAreNotPartOfTheSlot(): void
    {
        // E.g. after the times of the time slot have been changed
        $this->insertBooking(7, strtotime('2030-10-14 08:00'), strtotime('2030-10-14 09:30'));

        $this->assertSame([], $this->createProvider()->getBookings(7, strtotime('2030-10-14 08:00'), strtotime('2030-10-14 09:00')));
    }

    private function createProvider(): SlotDataProvider
    {
        return new SlotDataProvider($this->createStub(ContaoFramework::class), $this->createStub(TokenStorageInterface::class), $this->connection);
    }

    private function insertBooking(int $resource, int $startTime, int $endTime): int
    {
        return $this->insertRow('tl_resource_booking', ['pid' => $resource, 'startTime' => $startTime, 'endTime' => $endTime]);
    }
}
