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
 * Result of a cancellation (DELETE /_rbb/api/bookings/{id}).
 */
final class CancelResult implements ApiResultInterface
{
    /**
     * @param list<int> $cancelledBookingIds
     */
    public function __construct(
        public readonly bool $isCancelled,
        public readonly array $cancelledBookingIds = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'isCancelled' => $this->isCancelled,
            'cancelledBookingIds' => $this->cancelledBookingIds,
        ];
    }
}
