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

namespace Markocupic\ResourceBookingBundle\Tests\Notification;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Notification\TokenBuilder;
use Symfony\Component\Clock\MockClock;

class TokenBuilderTest extends ContaoTestCase
{
    public function testBuildsTheTokens(): void
    {
        $tokens = (new TokenBuilder())->build(
            ['id' => 5, 'firstname' => 'Max', 'lastname' => 'Müller', 'password' => 'secret', 'email' => 'max@example.com'],
            [
                ['id' => 1, 'title' => 'Mo, 16.11.2026: 08:00 - 08:45', 'description' => 'Projektwoche'],
                ['id' => 2, 'title' => 'Mo, 23.11.2026: 08:00 - 08:45', 'description' => 'Projektwoche'],
            ],
            ['id' => 3, 'title' => 'Werkraum'],
            ['id' => 4, 'title' => 'Räume'],
            'd.m.Y H:i',
            1_700_000_000,
        );

        $this->assertSame('Max', $tokens['booking_person_firstname']);
        $this->assertSame('Müller', $tokens['booking_person_lastname'], 'Umlauts must not be treated as binary values.');
        $this->assertArrayNotHasKey('booking_person_password', $tokens);

        $this->assertSame('1', $tokens['booking_id']);
        $this->assertSame('Projektwoche', $tokens['booking_description']);
        $this->assertArrayNotHasKey('booking_title', $tokens);
        $this->assertSame("Mo, 16.11.2026: 08:00 - 08:45\r\nMo, 23.11.2026: 08:00 - 08:45\r\n", $tokens['booking_details']);
        $this->assertStringContainsString('<br />', $tokens['booking_details_html']);
        $this->assertSame(date('d.m.Y H:i', 1_700_000_000), $tokens['booking_datim']);

        $this->assertSame('Werkraum', $tokens['booking_resource_title']);
        $this->assertSame('Räume', $tokens['booking_resource_type_title']);
    }

    public function testConvertsBinaryUuidsToStrings(): void
    {
        $uuid = '9e9d5bc9-6d1c-11ee-a5ed-0242ac120002';
        $binary = hex2bin(str_replace('-', '', $uuid));

        $tokens = (new TokenBuilder())->build(['singleSRC' => $binary, 'empty' => null], [], [], [], 'Y');

        $this->assertSame($uuid, $tokens['booking_person_singleSRC']);
        $this->assertSame('', $tokens['booking_person_empty']);
    }

    public function testTheDateOfTheBookingComesFromTheClock(): void
    {
        $tokens = (new TokenBuilder(new MockClock('@1795046400')))->build([], [], [], [], 'Y-m-d');

        $this->assertSame(date('Y-m-d', 1795046400), $tokens['booking_datim']);
    }
}
