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
 * Result of a booking request (POST /_rbb/api/bookings).
 */
final class BookingResult implements ApiResultInterface
{
    /**
     * @param list<array{id: int, timeSlotId: int, startTime: int, endTime: int}> $bookings The saved bookings
     */
    public function __construct(
        public readonly bool $isBooked,
        public readonly array $bookings = [],
    ) {
    }

    /**
     * @param iterable<array<string, mixed>> $rows Rows of tl_resource_booking
     */
    public static function fromBookingRows(iterable $rows): self
    {
        $bookings = [];

        foreach ($rows as $row) {
            $bookings[] = [
                'id' => (int) $row['id'],
                'timeSlotId' => (int) $row['timeSlotId'],
                'startTime' => (int) $row['startTime'],
                'endTime' => (int) $row['endTime'],
            ];
        }

        return new self(true, $bookings);
    }

    public function toArray(): array
    {
        return [
            'isBooked' => $this->isBooked,
            'bookings' => $this->bookings,
        ];
    }
}
