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

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\System;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingFormValidationController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    public const REQUEST_NAME = 'bookingFormValidationRequest';

    private SlotFactory $slotFactory;

    private TranslatorInterface $translator;

    private string|null $bookingUuid = null;

    /**
     * Use setter injection here.
     */
    #[Required]
    public function _setController(SlotFactory $slotFactory, TranslatorInterface $translator): void
    {
        $this->slotFactory = $slotFactory;
        $this->translator = $translator;
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework
            ->getAdapter(System::class)
            ->loadLanguageFile('default', $this->translator->getLocale())
        ;

        $this->initialize();

        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setData('noDatesSelected', false);
        $ajaxResponse->setData('resourceIsAlreadyFullyBooked', false);
        $ajaxResponse->setData('bookingValidationProcessSucceeded', true);
        $ajaxResponse->setData('noBookingRepeatStopWeekTstampSelected', false);

        $slots = $this->getSlotCollectionFromRequest($this->bookingRepeatStopWeekTstamp);

        if ($this->isBookingPossible($slots)) {
            $ajaxResponse->setConfirmationMessage($this->translator->trans('RBB.MSG.resourceAvailable', [], 'contao_default'));
        } else {
            $ajaxResponse->setData('bookingValidationProcessSucceeded', false);
            $this->setSlotErrorMessage($ajaxResponse, $slots);
        }

        $ajaxResponse->setData('slotSelection', $slots->fetchAll());

        if ($ajaxResponse->hasErrorMessage()) {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        }

        return $ajaxResponse;
    }

    private function setSlotErrorMessage(AjaxResponse $ajaxResponse, SlotCollection|null $slots): void
    {
        if ($this->hasErrorMessage()) {
            $ajaxResponse->setErrorMessage($this->translator->trans($this->getErrorMessage(), [], 'contao_default'));

            return;
        }

        if (empty($slots)) {
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.selectBookingDatesPlease', [], 'contao_default'));

            return;
        }

        $slots->reset();

        while ($slots->next()) {
            $slot = $slots->current();

            if (true === $slot->invalidDate) {
                $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.selectBookingDatesPlease', [], 'contao_default'));

                return;
            }

            if (!$slot->isBookable) {
                $key = match (true) {
                    $slot->isFullyBooked => 'RBB.ERR.resourceIsAlreadyFullyBooked',
                    $slot->isBlocked => 'RBB.ERR.slotIsBlocked',
                    default => 'RBB.ERR.notEnoughItemsAvailable',
                };
                $ajaxResponse->setErrorMessage($this->translator->trans($key, [], 'contao_default'));

                return;
            }
        }
    }
}
