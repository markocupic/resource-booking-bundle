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

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Contao\Controller;
use Contao\Validator;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Util\DateHelper;

class RegExpListener
{
    public const REGEX_RESOURCE_BOOKING_TIME = 'resourceBookingTime';

    #[AsHook('addCustomRegexp')]
    public function onCustomRegexp(string $strRegexp, string $varValue, Widget $objWidget): bool
    {
        if (self::REGEX_RESOURCE_BOOKING_TIME === $strRegexp) {
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
