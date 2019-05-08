<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */

namespace Markocupic\ResourceReservationBundle;

/**
 * Class ResourceReservationHelper
 * @package Markocupic\ResourceReservationBundle
 */
class ResourceReservationHelper
{

    /**
     * @param $strFormat
     * @param $tstamp
     * @return string
     */
    public static function parseToUtcDate($strFormat, $tstamp)
    {
        $strValue = '';
        if ($tstamp != '')
        {
            $dt = new \DateTime();
            $dt->setTimestamp($tstamp);
            $dt->setTimezone(new \DateTimeZone("UTC"));
            $strValue = $dt->format($strFormat);
        }
        return $strValue;
    }
}
