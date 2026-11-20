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

namespace Markocupic\ResourceBookingBundle\Tests\Twig;

use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Twig\BookingMemberExtension;
use Symfony\Bundle\SecurityBundle\Security;

class BookingMemberExtensionTest extends ContaoTestCase
{
    private const MEMBER = [
        'id' => 7,
        'firstname' => 'Anna',
        'email' => 'anna@example.org',
        'company' => 'Muster &amp; Co',
        'password' => 'hash',
        'secret' => 'totp',
        'session' => 'serialized',
    ];

    public function testRegistersTheFunction(): void
    {
        $functions = $this->createExtension()->getFunctions();

        $this->assertSame('rbb_booking_member', $functions[0]->getName());
    }

    public function testReturnsTheMemberOfTheBookingWithoutForbiddenFields(): void
    {
        $data = $this->createExtension()->getBookingMember(['id' => 12]);

        $this->assertSame('anna@example.org', $data['email']);
        $this->assertSame('Muster & Co', $data['company']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayNotHasKey('session', $data);
    }

    public function testAcceptsTheIdOfTheBookingAndCachesTheResult(): void
    {
        $extension = $this->createExtension(expectedLookups: 1);

        $this->assertSame('Anna', $extension->getBookingMember(12)['firstname']);
        $this->assertSame('Anna', $extension->getBookingMember(['id' => '12'])['firstname']);
    }

    public function testReturnsNullForGuests(): void
    {
        $this->assertNull($this->createExtension(loggedIn: false)->getBookingMember(['id' => 12]));
    }

    public function testReturnsNullIfTheBookingOrTheMemberDoesNotExist(): void
    {
        $extension = $this->createExtension();

        $this->assertNull($extension->getBookingMember(['id' => 99]));
        $this->assertNull($extension->getBookingMember(['id' => 13]));
        $this->assertNull($extension->getBookingMember([]));
    }

    private function createExtension(bool $loggedIn = true, int|null $expectedLookups = null): BookingMemberExtension
    {
        $security = $this->createStub(Security::class);
        $security
            ->method('getUser')
            ->willReturn($loggedIn ? $this->createClassWithPropertiesStub(FrontendUser::class, ['id' => 1]) : null)
        ;

        $bookingAdapter = null === $expectedLookups ? $this->createAdapterStub(['findById']) : $this->createAdapterMock(['findById']);

        if (null !== $expectedLookups) {
            $bookingAdapter
                ->expects($this->exactly($expectedLookups))
                ->method('findById')
                ->willReturnCallback($this->findBooking(...))
            ;
        } else {
            $bookingAdapter
                ->method('findById')
                ->willReturnCallback($this->findBooking(...))
            ;
        }

        $member = $this->createStub(MemberModel::class);
        $member
            ->method('row')
            ->willReturn(self::MEMBER)
        ;

        $memberAdapter = $this->createAdapterStub(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturnCallback(static fn (int $id): MemberModel|null => 7 === $id ? $member : null)
        ;

        $framework = $this->createContaoFrameworkStub([
            ResourceBookingModel::class => $bookingAdapter,
            MemberModel::class => $memberAdapter,
        ]);

        return new BookingMemberExtension($framework, $security);
    }

    private function findBooking(int $id): ResourceBookingModel|null
    {
        // Booking 12 of member 7, booking 13 of a deleted member
        return match ($id) {
            12 => $this->createClassWithPropertiesStub(ResourceBookingModel::class, ['id' => 12, 'member' => 7]),
            13 => $this->createClassWithPropertiesStub(ResourceBookingModel::class, ['id' => 13, 'member' => 8]),
            default => null,
        };
    }
}
