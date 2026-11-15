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

namespace Markocupic\ResourceBookingBundle\NotificationType;

/**
 * Notification that is sent after a booking has been cancelled.
 */
class CancellationNotificationType extends AbstractResourceBookingNotificationType
{
    public const string NAME = 'resource-booking-cancellation-notification';

    public function getName(): string
    {
        return self::NAME;
    }
}
