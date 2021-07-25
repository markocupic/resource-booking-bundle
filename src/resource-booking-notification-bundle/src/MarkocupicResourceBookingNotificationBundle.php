<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Notification Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license LGPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-notification-bundle
 */

namespace Markocupic\ResourceBookingNotificationBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MarkocupicResourceBookingNotificationBundle.
 */
class MarkocupicResourceBookingNotificationBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
