<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Validator;
use Contao\Widget;

/**
 * Class RegexpHook
 * @package Markocupic\ResourceBookingBundle
 */
class RegexpHook
{

    /**
     * @param string $strRegexp
     * @param string $varValue
     * @param Widget $objWidget
     * @return bool
     */
    public function customRegexp(string $strRegexp, string $varValue, Widget $objWidget): bool
    {
        if ($strRegexp === 'resourceBookingTime')
        {
            if (!Validator::isTime($varValue))
            {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            if(!DateHelper::isValidBookingTime($varValue))
            {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            return true;
        }

        return false;
    }
}
