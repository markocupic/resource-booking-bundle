<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Date;

/**
 * Class UtcDate
 * @package Markocupic\ResourceBookingBundle
 */
class UtcDate
{

    /**
     * Return a formated time/date string based on the UTC timezone
     * @param $strFormat
     * @param $tstamp
     * @return string
     */
    public static function parse(string $strFormat, int $tstamp): string
    {
        return gmdate($strFormat, $tstamp);
    }

    /**
     * Return a timestamp based on the UTC timezone
     * @param string $strDate
     * @return int
     */
    public static function strtotime(string $strDate): int
    {
        $utc = new \DateTimeZone('UTC');
        $dt = new \DateTime($strDate, $utc);
        return $dt->format('U');
    }
}
