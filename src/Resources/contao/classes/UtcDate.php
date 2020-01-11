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
     * @param $strFormat
     * @param $tstamp
     * @return string
     */
    public static function parse(string $strFormat, int $tstamp): string
    {
        date_default_timezone_set('UTC');
        $strValue = Date::parse($strFormat, $tstamp);
        date_default_timezone_set($GLOBALS['TL_CONFIG']['timeZone']);

        return $strValue;
    }

    /**
     * @param string $strDate
     * @return int
     */
    public static function strtotime(string $strDate): int
    {
        date_default_timezone_set('UTC');
        $timestamp = strtotime($strDate);
        date_default_timezone_set($GLOBALS['TL_CONFIG']['timeZone']);

        return $timestamp;
    }
}
