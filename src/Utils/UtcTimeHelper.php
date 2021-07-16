<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Utils;

/**
 * Class UtcTimeHelper.
 */
class UtcTimeHelper
{
    /**
     * Return a formated time/date string based on the UTC timezone.
     *
     * @param $strFormat
     * @param $tstamp
     */
    public static function parse(string $strFormat, int $tstamp): string
    {
        return gmdate($strFormat, $tstamp);
    }

    /**
     * Return a timestamp based on the UTC timezone.
     */
    public static function strtotime(string $strDate): int
    {
        $utc = new \DateTimeZone('UTC');
        $dt = new \DateTime($strDate, $utc);

        return (int) $dt->format('U');
    }
}
