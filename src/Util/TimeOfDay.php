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

namespace Markocupic\ResourceBookingBundle\Util;

final class TimeOfDay
{
    private function __construct()
    {
    }

    /**
     * Converts a time into seconds since midnight. The time is either a string
     * "hh:mm" (00:00 - 24:00) or a Contao time value (timestamp of 1970-01-01
     * in the local time zone, as stored by fields with rgxp "time").
     * Returns null for an empty or invalid time.
     */
    public static function toSeconds(mixed $time): int|null
    {
        if (null === $time || '' === $time) {
            return null;
        }

        if (\is_int($time) || (\is_string($time) && preg_match('/^-?\d+$/', $time))) {
            $time = date('H:i', (int) $time);
        }

        if (!\is_string($time) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            return null;
        }

        $seconds = (int) $matches[1] * 3600 + (int) $matches[2] * 60;

        return $seconds <= 86400 ? $seconds : null;
    }
}
