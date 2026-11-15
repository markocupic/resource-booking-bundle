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

namespace Markocupic\ResourceBookingBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;
use Terminal42\NotificationCenterBundle\NotificationCenter;

/**
 * Only notifications of the matching type can be selected in the resource settings.
 */
class ResourceNotificationOptionsListener
{
    public function __construct(private readonly NotificationCenter $notificationCenter)
    {
    }

    /**
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_resource_booking_resource', target: 'fields.bookingNotification.options')]
    public function getBookingNotifications(): array
    {
        return $this->notificationCenter->getNotificationsForNotificationType(BookingNotificationType::NAME);
    }

    /**
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_resource_booking_resource', target: 'fields.cancellationNotification.options')]
    public function getCancellationNotifications(): array
    {
        return $this->notificationCenter->getNotificationsForNotificationType(CancellationNotificationType::NAME);
    }
}
