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

namespace Markocupic\ResourceBookingBundle\DependencyInjection\Attribute;

/**
 * Registers an Ajax controller of the week calendar for an action, e.g.
 * #[AsAjaxController('myCustomRequest')]. The class must implement
 * Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface.
 *
 * The action is the "action" parameter sent by the front end. Each action
 * can only be answered by one controller.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsAjaxController
{
    public const TAG = 'markocupic_resource_booking.ajax_controller';

    public function __construct(public readonly string $action)
    {
    }
}
