<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */

namespace Markocupic\ResourceReservationBundle;

use Contao\Date;

/**
 * Class DateHelper
 * @package Markocupic\ResourceReservationBundle
 */
class DateHelper
{

    /**
     * @param $intDays
     */
    public static function addDaysToTime($intDays = 0, $time = null)
    {
        if ($time === null)
        {
            $time = time();
        }
        if ($intDays < 0)
        {
            $intDays = $intDays * (-1);
            $strAddDays = '- ' . $intDays . ' days';
        }
        else
        {
            $strAddDays = '+ ' . $intDays . ' days';
        }

        return strtotime(Date::parse('Y-m-d H:i:s', $time) . ' ' . $strAddDays);
    }

    /**
     * @return false|int
     */
    public static function getMondayOfCurrentWeek()
    {
        return strtotime('monday this week');
    }

}
