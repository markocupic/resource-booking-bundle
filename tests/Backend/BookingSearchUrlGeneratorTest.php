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

namespace Markocupic\ResourceBookingBundle\Tests\Backend;

use Markocupic\ResourceBookingBundle\Backend\BookingSearchUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BookingSearchUrlGeneratorTest extends TestCase
{
    public function testGeneratesAnAbsoluteUrlToTheBookingSearch(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('contao_backend', ['rbb_booking_uuid' => '0f8f2c4e-1b2c'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/contao?rbb_booking_uuid=0f8f2c4e-1b2c')
        ;

        $this->assertSame('https://example.com/contao?rbb_booking_uuid=0f8f2c4e-1b2c', (new BookingSearchUrlGenerator($router))->generate(' 0f8f2c4e-1b2c '));
    }

    public function testReturnsAnEmptyStringWithoutABookingUuid(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router
            ->expects($this->never())
            ->method('generate')
        ;

        $this->assertSame('', (new BookingSearchUrlGenerator($router))->generate(''));
    }
}
