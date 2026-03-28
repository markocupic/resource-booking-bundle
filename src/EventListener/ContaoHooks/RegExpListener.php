<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Validator;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Util\DateHelper;

class RegExpListener
{
    public const REGEX_RESOURCE_BOOKING_TIME = 'resourceBookingTime';

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    #[AsHook('addCustomRegexp')]
    public function onCustomRegexp(string $strRegexp, string $varValue, Widget $objWidget): bool
    {
        if (self::REGEX_RESOURCE_BOOKING_TIME === $strRegexp) {
            $this->framework
                ->getAdapter(Controller::class)
                ->loadLanguageFile('default')
            ;

            if (!$this->getValidator()->isTime($varValue)) {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            if (!$this->getDateHelper()->isValidBookingTime($varValue)) {
                $objWidget->addError($GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime']);
            }

            return true;
        }

        return false;
    }

    private function getValidator(): Adapter
    {
        return $this->framework->getAdapter(Validator::class);
    }

    private function getDateHelper(): Adapter
    {
        return $this->framework->getAdapter(DateHelper::class);
    }
}
