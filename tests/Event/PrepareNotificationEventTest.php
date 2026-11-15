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

namespace Markocupic\ResourceBookingBundle\Tests\Event;

use Markocupic\ResourceBookingBundle\Event\PrepareNotificationEvent;
use PHPUnit\Framework\TestCase;

class PrepareNotificationEventTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $event = new PrepareNotificationEvent('type', 'week_calendar', 11, ['a' => '1'], 'de', [['id' => 1]], ['id' => 3], ['id' => 4], ['id' => 5]);

        $this->assertSame('type', $event->getNotificationType());
        $this->assertSame('week_calendar', $event->getContext());
        $this->assertSame(11, $event->getNotificationId());
        $this->assertSame(['a' => '1'], $event->getTokens());
        $this->assertSame('de', $event->getLocale());
        $this->assertSame([['id' => 1]], $event->getBookings());
        $this->assertSame(['id' => 3], $event->getResource());
        $this->assertSame(['id' => 4], $event->getResourceType());
        $this->assertSame(['id' => 5], $event->getMember());
        $this->assertFalse($event->isCancelled());

        $event->setNotificationId(12)->setToken('b', '2')->setLocale(null);

        $this->assertSame(12, $event->getNotificationId());
        $this->assertSame(['a' => '1', 'b' => '2'], $event->getTokens());
        $this->assertNull($event->getLocale());

        $event->setTokens(['c' => '3']);
        $this->assertSame(['c' => '3'], $event->getTokens());
    }

    public function testCancelStopsThePropagation(): void
    {
        $event = new PrepareNotificationEvent('type', 'week_calendar', 11, [], null, [], [], [], []);
        $event->cancel();

        $this->assertTrue($event->isCancelled());
        $this->assertTrue($event->isPropagationStopped());
    }
}
