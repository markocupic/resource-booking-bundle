<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Contao\Controller;
use Contao\Validator;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Helper\DateHelper;

/**
 * Class Regexp.
 */
class Regexp
{
    public function onCustomRegexp(string $strRegexp, string $varValue, Widget $objWidget): bool
    {
        if ('resourceBookingTime' === $strRegexp) {
            Controller::loadLanguageFile('default');

            if (!Validator::isTime($varValue)) {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            if (!DateHelper::isValidBookingTime($varValue)) {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            return true;
        }

        return false;
    }
}
