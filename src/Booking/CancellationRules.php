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

namespace Markocupic\ResourceBookingBundle\Booking;

/**
 * Who can cancel a booking and until when (the server must not rely on the front end
 * hiding the button).
 */
final class CancellationRules
{
    private function __construct()
    {
    }

    /**
     * A booking can be canceled until its end (same rule as Slot::isInBookingWindow()).
     *
     * @param array<string, mixed> $booking Row of tl_resource_booking
     */
    public static function isCancelableByTime(array $booking, int $currentTime): bool
    {
        return (int) ($booking['endTime'] ?? 0) >= $currentTime;
    }

    /**
     * Only the member who booked can cancel the booking.
     *
     * @param array<string, mixed> $booking Row of tl_resource_booking
     */
    public static function isOwnedBy(array $booking, int $memberId): bool
    {
        return $memberId > 0 && (int) ($booking['member'] ?? 0) === $memberId;
    }
}
