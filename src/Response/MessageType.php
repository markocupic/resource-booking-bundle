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

namespace Markocupic\ResourceBookingBundle\Response;

/**
 * Message types of an Ajax response (JSON key "messages"). The front end displays
 * them above the week calendar or in the booking dialog.
 */
enum MessageType: string
{
    case Warning = 'warning';
    case Error = 'error';
    case Confirm = 'confirm';
    case Info = 'info';
}
