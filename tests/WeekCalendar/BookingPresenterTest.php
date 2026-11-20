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

namespace Markocupic\ResourceBookingBundle\Tests\WeekCalendar;

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\WeekCalendar\BookingPresenter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingPresenterTest extends TestCase
{
    private const BOOKING = [
        'id' => '12',
        'pid' => '3',
        'member' => '7',
        'itemsBooked' => '2',
        'bookingTime' => '1795420800',
        'confirmed' => '0',
        'title' => 'Turnhalle: Anna Muster',
        'description' => 'Training &amp; Spiel',
        'bookingUuid' => 'secret-uuid',
        'moduleId' => '5',
    ];

    private const MEMBER = [
        'id' => '7',
        'firstname' => 'Anna',
        'lastname' => 'Muster',
        'city' => 'Hochfelden',
        'email' => 'anna@example.org',
        'password' => 'hash',
    ];

    public function testOnlyPermittedFieldsAreSent(): void
    {
        $data = $this->createPresenter()->present(self::BOOKING, self::MEMBER, 99, ['firstname', 'lastname', 'city'], ['description'], true);

        $expectedKeys = [
            ...RbbConfig::RBB_ALWAYS_SENT_BOOKING_FIELDS,
            'bookedByFullname', 'bookedByAnonymous', 'bookedByDetails', 'bookedByFirstname', 'bookedByLastname', 'bookedByCity',
            'bookingDescription', 'isOwn', 'canCancel',
        ];

        $this->assertEqualsCanonicalizing(array_unique($expectedKeys), array_keys($data));
        $this->assertArrayNotHasKey('bookedByEmail', $data);
        $this->assertArrayNotHasKey('bookingUuid', $data);
        $this->assertSame('Hochfelden', $data['bookedByCity']);
        $this->assertSame('Training & Spiel', $data['bookingDescription']);
    }

    public function testTheDisplayedMemberFieldsApartFromTheNameAreListedWithTheirLabels(): void
    {
        $member = [...self::MEMBER, 'company' => 'Muster &amp; Co', 'phone' => ''];

        $data = $this->createPresenter()->present(self::BOOKING, $member, 99, ['firstname', 'lastname', 'company', 'city', 'phone'], [], false);

        // Order of the module settings, no name, no empty values; label from the language file (or the field name)
        $this->assertSame(
            [
                ['field' => 'company', 'label' => 'Firma', 'value' => 'Muster & Co'],
                ['field' => 'city', 'label' => 'city', 'value' => 'Hochfelden'],
            ],
            $data['bookedByDetails'],
        );
    }

    public function testNoMemberDetailsWithoutDisplayedFields(): void
    {
        $presenter = $this->createPresenter();

        $this->assertSame([], $presenter->present(self::BOOKING, self::MEMBER, 7, [], [], true)['bookedByDetails']);
        $this->assertSame([], $presenter->present(self::BOOKING, null, 99, ['city'], [], true)['bookedByDetails']);
    }

    public function testCastsTheTypesForJavaScript(): void
    {
        $data = $this->createPresenter()->present(self::BOOKING, self::MEMBER, null, [], [], false);

        $this->assertSame(12, $data['id']);
        $this->assertSame(3, $data['pid']);
        $this->assertSame(2, $data['itemsBooked']);
        $this->assertSame(1795420800, $data['bookingTime']);
        // "0" would be true in JavaScript
        $this->assertFalse($data['confirmed']);
    }

    public function testNameIsAnonymousIfNotPermitted(): void
    {
        $data = $this->createPresenter()->present(self::BOOKING, self::MEMBER, 99, ['city'], [], false);

        $this->assertSame('Anonym', $data['bookedByFullname']);
        $this->assertTrue($data['bookedByAnonymous']);
        $this->assertArrayNotHasKey('bookedByFirstname', $data);
    }

    public function testTheLoggedInMemberAlwaysSeesTheirOwnName(): void
    {
        $data = $this->createPresenter()->present(self::BOOKING, self::MEMBER, 7, [], [], true);

        $this->assertSame('Anna Muster', $data['bookedByFullname']);
        $this->assertFalse($data['bookedByAnonymous']);
        $this->assertTrue($data['isOwn']);
        $this->assertTrue($data['canCancel']);
    }

    public function testOnlyOwnBookingsCanBeCanceled(): void
    {
        $presenter = $this->createPresenter();

        // Booking of another member
        $this->assertFalse($presenter->present(self::BOOKING, self::MEMBER, 99, [], [], true)['canCancel']);

        // Own booking, but the slot cannot be canceled (e.g. in the past)
        $this->assertFalse($presenter->present(self::BOOKING, self::MEMBER, 7, [], [], false)['canCancel']);

        // Guest
        $this->assertFalse($presenter->present(self::BOOKING, self::MEMBER, null, [], [], true)['isOwn']);
    }

    public function testBookingOfADeletedMember(): void
    {
        $data = $this->createPresenter()->present(self::BOOKING, null, 7, ['firstname', 'lastname'], [], true);

        $this->assertSame('Anonym', $data['bookedByFullname']);
        $this->assertTrue($data['bookedByAnonymous']);
        $this->assertFalse($data['canCancel']);
    }

    private function createPresenter(): BookingPresenter
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id): string => ['RBB.anonymous' => 'Anonym', 'tl_member.company.0' => 'Firma'][$id] ?? $id)
        ;

        return new BookingPresenter($translator);
    }
}
