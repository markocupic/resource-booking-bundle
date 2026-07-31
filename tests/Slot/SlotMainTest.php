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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Markocupic\ResourceBookingBundle\Slot\AbstractSlot;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\Utils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Exercises the booking arithmetic inherited from AbstractSlot through the
 * concrete SlotMain. Bookings and resource are injected via setRow() so the
 * methods run without database access or time() dependencies.
 */
class SlotMainTest extends TestCase
{
    public function testIsFullyBookedReflectsBookedItemsAgainstCapacity(): void
    {
        // 2 of 3 items booked -> not fully booked.
        $slot = $this->slotWith(3, [['itemsBooked' => 1], ['itemsBooked' => 1]]);
        $this->assertFalse($slot->isFullyBooked());

        // 3 of 3 items booked -> fully booked.
        $slot = $this->slotWith(3, [['itemsBooked' => 2], ['itemsBooked' => 1]]);
        $this->assertTrue($slot->isFullyBooked());
    }

    public function testCanFulfillRequestedItemsChecksRemainingCapacity(): void
    {
        // 2 booked + 1 requested = 3, capacity 3 -> fits.
        $slot = $this->slotWith(3, [['itemsBooked' => 1], ['itemsBooked' => 1]], requestedItems: 1);
        $this->assertTrue($slot->canFulfillRequestedItems());

        // 2 booked + 1 requested = 3, capacity 2 -> does not fit.
        $slot = $this->slotWith(2, [['itemsBooked' => 1], ['itemsBooked' => 1]], requestedItems: 1);
        $this->assertFalse($slot->canFulfillRequestedItems());
    }

    public function testBookingsWithoutExplicitItemCountAreCountedAsOne(): void
    {
        // Two bookings without "itemsBooked" default to 1 each -> 2 booked of 2 -> fully booked.
        $slot = $this->slotWith(2, [[], []]);

        $this->assertTrue($slot->isFullyBooked());
    }

    public function testOwnBookingIsRecognizedWhenMemberIdIsStoredAsString(): void
    {
        // The logged-in user's own booking must not count against the capacity,
        // even if the "member" value comes back from the DB as a string.
        $slot = $this->slotWith(
            2,
            [
                ['itemsBooked' => 1, 'member' => '5'], // own booking (string id)
                ['itemsBooked' => 1, 'member' => 9],
            ],
            requestedItems: 1,
        );

        $this->setLoggedInUserId($slot, 5);

        // Own booking excluded => 1 booked + 1 requested = 2 <= 2 => fits.
        $this->assertTrue($slot->canFulfillRequestedItems());
    }

    private function setLoggedInUserId(SlotMain $slot, int $id): void
    {
        $user = $this->createMock(MemberModel::class);
        $user
            ->method('__get')
            ->willReturnCallback(static fn (string $key): mixed => 'id' === $key ? $id : null)
        ;

        $property = new \ReflectionProperty(AbstractSlot::class, 'user');
        $property->setAccessible(true);
        $property->setValue($slot, $user);
    }

    /**
     * @param array<int, array<string, mixed>> $bookings
     */
    private function slotWith(int $itemsAvailable, array $bookings, int $requestedItems = 1): SlotMain
    {
        $slot = new SlotMain(
            $this->createMock(ContaoFramework::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(Utils::class),
        );

        $slot->setRow([
            'resource' => ['itemsAvailable' => $itemsAvailable],
            'itemsBooked' => $requestedItems,
            'bookings' => $bookings,
        ]);

        return $slot;
    }
}
