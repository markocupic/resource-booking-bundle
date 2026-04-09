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
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbEndTime;
use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbStartTime;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegExpListener
{
    public const REGEX_RESOURCE_BOOKING_START_TIME = 'rbbStartTime';

    public const REGEX_RESOURCE_BOOKING_END_TIME = 'rbbEndTime';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[AsHook('addCustomRegexp')]
    public function onCustomRegexp(string $strRegexp, string $varValue, Widget $objWidget): bool
    {
        if (self::REGEX_RESOURCE_BOOKING_START_TIME === $strRegexp) {
            $this->framework
                ->getAdapter(Controller::class)
                ->loadLanguageFile('default')
            ;

            $violations = $this->validator->validate($varValue, new RbbStartTime());

            if (\count($violations) > 0) {
                $objWidget->addError($GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingStartTime']);
            }

            if (!$this->getDateHelper()->isValidBookingTime($varValue)) {
                $objWidget->addError($GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingStartTime']);
            }

            return true;
        }

        if (self::REGEX_RESOURCE_BOOKING_END_TIME === $strRegexp) {
            $this->framework
                ->getAdapter(Controller::class)
                ->loadLanguageFile('default')
            ;

            $violations = $this->validator->validate($varValue, new RbbEndTime());

            if (\count($violations) > 0) {
                $objWidget->addError($GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingEndTime']);
            }

            if (!$this->getDateHelper()->isValidBookingTime($varValue)) {
                $objWidget->addError($GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingEndTime']);
            }

            return true;
        }

        return false;
    }

    private function getDateHelper(): Adapter
    {
        return $this->framework->getAdapter(DateHelper::class);
    }
}
