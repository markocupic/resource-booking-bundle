<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
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
    public static function parse($strFormat, $tstamp)
    {
        if ($tstamp == '')
        {
            $tstamp = time();
        }
        $strValue = '';

        date_default_timezone_set('UTC');
        $strValue = Date::parse($strFormat, $tstamp);
        date_default_timezone_set($GLOBALS['TL_CONFIG']['timeZone']);

        return $strValue;
    }

    /**
     * @param $strDate
     * @return false|int
     */
    public static function strtotime($strDate)
    {
        $strValue = '';
        date_default_timezone_set('UTC');
        $timestamp = strtotime($strDate);
        date_default_timezone_set($GLOBALS['TL_CONFIG']['timeZone']);

        return $timestamp;
    }
}
