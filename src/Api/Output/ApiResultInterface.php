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

namespace Markocupic\ResourceBookingBundle\Api\Output;

/**
 * Result of an API action: the data part of the JSON response
 * (see AjaxResponse::setResult()).
 */
interface ApiResultInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
