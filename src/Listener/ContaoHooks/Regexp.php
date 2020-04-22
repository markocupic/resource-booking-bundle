<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Listener\ContaoHooks;

use Contao\Controller;
use Contao\Validator;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Date\DateHelper;

/**
 * Class Regexp
 * @package Markocupic\ResourceBookingBundle\Listener\ContaoHooks
 */
class Regexp
{
    /**
     * @param string $strRegexp
     * @param string $varValue
     * @param Widget $objWidget
     * @return bool
     */
    public function onCustomRegexp(string $strRegexp, string $varValue, Widget $objWidget): bool
    {
        if ($strRegexp === 'resourceBookingTime')
        {
            Controller::loadLanguageFile('default');
            if (!Validator::isTime($varValue))
            {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            if (!DateHelper::isValidBookingTime($varValue))
            {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            return true;
        }

        return false;
    }
}
