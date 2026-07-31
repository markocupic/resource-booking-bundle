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

namespace Markocupic\ResourceBookingBundle\Tests\Session;

use Markocupic\ResourceBookingBundle\Session\SessionFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionFactoryTest extends TestCase
{
    public function testCreateSessionRegistersTheBagAndReturnsTheInnerSession(): void
    {
        $bag = $this->createMock(SessionBagInterface::class);

        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('registerBag')
            ->with($bag)
        ;

        $inner = $this->createMock(SessionFactoryInterface::class);
        $inner
            ->expects($this->once())
            ->method('createSession')
            ->willReturn($session)
        ;

        $factory = new SessionFactory($inner, $bag);

        $this->assertSame($session, $factory->createSession());
    }
}
