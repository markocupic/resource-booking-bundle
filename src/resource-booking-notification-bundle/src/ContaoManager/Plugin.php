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

namespace Markocupic\ResourceBookingNotificationBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Markocupic\ResourceBookingBundle\MarkocupicResourceBookingBundle;
use Markocupic\ResourceBookingNotificationBundle\MarkocupicResourceBookingNotificationBundle;

/**
 * Class Plugin.
 */
class Plugin implements BundlePluginInterface
{
    /**
     * @return array
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(MarkocupicResourceBookingNotificationBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class,
                    MarkocupicResourceBookingBundle::class,
                ]),
        ];
    }
}
