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
 * Result of the booking preview (POST /_rbb/api/bookings/validate): can all
 * selected slots and their repetitions be booked?
 */
final class BookingPreview implements ApiResultInterface
{
    /**
     * @param list<array<string, mixed>> $slots Presented slots (SlotPresenter), without bookings of other members
     */
    public function __construct(
        public readonly bool $isBookable,
        public readonly array $slots = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'isBookable' => $this->isBookable,
            'slots' => $this->slots,
        ];
    }
}
