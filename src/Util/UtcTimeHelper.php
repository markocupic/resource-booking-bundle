<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Util;

class UtcTimeHelper
{
    /**
     * Return a formatted time/date string based on the UTC timezone.
     */
    public static function parse(string $strFormat, int $tstamp): string
    {
        return gmdate($strFormat, $tstamp);
    }

    /**
     * Return a timestamp based on the UTC timezone.
     *
     * @throws \Exception
     */
    public static function strToTime(string $strDate): int
    {
        $utc = new \DateTimeZone('UTC');
        $dt = new \DateTime($strDate, $utc);

        return (int) $dt->format('U');
    }
}
