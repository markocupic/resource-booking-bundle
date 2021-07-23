<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;

interface ControllerInterface
{
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent);
}
