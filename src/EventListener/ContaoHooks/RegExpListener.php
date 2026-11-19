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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbEndTime;
use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbStartTime;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegExpListener
{
    public const REGEX_RESOURCE_BOOKING_START_TIME = 'rbbStartTime';

    public const REGEX_RESOURCE_BOOKING_END_TIME = 'rbbEndTime';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Validates the start and end time of the time slots (tl_resource_booking_time_slot).
     */
    #[AsHook('addCustomRegexp')]
    public function onCustomRegexp(string $regexp, string $value, Widget $widget): bool
    {
        [$constraint, $message] = match ($regexp) {
            self::REGEX_RESOURCE_BOOKING_START_TIME => [new RbbStartTime(), 'RBB.MSG.pleaseInsertValidBookingStartTime'],
            self::REGEX_RESOURCE_BOOKING_END_TIME => [new RbbEndTime(), 'RBB.MSG.pleaseInsertValidBookingEndTime'],
            default => [null, null],
        };

        if (null === $constraint) {
            return false;
        }

        $this->framework->getAdapter(Controller::class)->loadLanguageFile('default');

        if (\count($this->validator->validate($value, $constraint)) > 0 || !DateHelper::isValidBookingTime($value)) {
            $widget->addError($this->translator->trans($message, [], 'contao_default'));
        }

        return true;
    }
}
