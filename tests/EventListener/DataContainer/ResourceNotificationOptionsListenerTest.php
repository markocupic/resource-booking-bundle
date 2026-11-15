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

namespace Markocupic\ResourceBookingBundle\Tests\EventListener\DataContainer;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\EventListener\DataContainer\ResourceNotificationOptionsListener;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class ResourceNotificationOptionsListenerTest extends ContaoTestCase
{
    public function testReturnsTheNotificationsOfTheMatchingType(): void
    {
        $notificationCenter = $this->createStub(NotificationCenter::class);
        $notificationCenter
            ->method('getNotificationsForNotificationType')
            ->willReturnMap([
                ['resource-booking-notification', [1 => 'Buchung Admin']],
                ['resource-booking-cancellation-notification', [2 => 'Stornierung Admin']],
            ])
        ;

        $listener = new ResourceNotificationOptionsListener($notificationCenter);

        $this->assertSame([1 => 'Buchung Admin'], $listener->getBookingNotifications());
        $this->assertSame([2 => 'Stornierung Admin'], $listener->getCancellationNotifications());
    }
}
