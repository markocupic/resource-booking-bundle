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

namespace Markocupic\ResourceBookingBundle\Api\Input;

/**
 * A time slot selected in the week calendar (booking request).
 */
final class SelectedSlot
{
    public function __construct(
        public readonly int $timeSlotId,
        public readonly int $startTime,
        public readonly int $endTime,
    ) {
    }
}
