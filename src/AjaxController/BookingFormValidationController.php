<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\System;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BookingFormValidationController.
 */
final class BookingFormValidationController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    private SlotFactory $slotFactory;
    private TranslatorInterface $translator;
    private ?string $bookingUuid = null;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(SlotFactory $slotFactory, TranslatorInterface $translator): void
    {
        $this->slotFactory = $slotFactory;
        $this->translator = $translator;
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        $this->initialize();

        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setData('noDatesSelected', false);
        $ajaxResponse->setData('resourceIsAlreadyFullyBooked', false);
        $ajaxResponse->setData('bookingValidationProcessSucceeded', false);
        $ajaxResponse->setData('noBookingRepeatStopWeekTstampSelected', false);
        $ajaxResponse->setData('bookingValidationProcessSucceeded', true);

        $slotCollection = $this->getSlotCollectionFromRequest();

        if (!$this->isBookingPossible($slotCollection)) {
            $ajaxResponse->setData('bookingValidationProcessSucceeded', false);

            if ($this->hasErrorMessage()) {
                $ajaxResponse->setErrorMessage($this->translator->trans($this->getErrorMessage(), [], 'contao_default'));
            }

            if (empty($slotCollection)) {
                $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.selectBookingDatesPlease', [], 'contao_default'));
            } else {
                $slotCollection->reset();

                while ($slotCollection->next()) {
                    $slot = $slotCollection->next();

                    if (true === $slot->invalidDate) {
                        $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.selectBookingDatesPlease', [], 'contao_default'));
                        break;
                    }

                    if (!$slot->isBookable) {
                        if ($slot->isFullyBooked) {
                            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.resourceIsAlreadyFullyBooked', [], 'contao_default'));
                        } else {
                            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.notEnoughItemsAvailable', [], 'contao_default'));
                        }
                        $ajaxResponse->setData('bookingValidationProcessSucceeded', false);
                        break;
                    }
                }
            }
        } else {
            $ajaxResponse->setConfirmationMessage($this->translator->trans('RBB.MSG.resourceAvailable', [], 'contao_default'));
        }

        $ajaxResponse->setData('slotSelection', $slotCollection->fetchAll());

        if ($this->hasErrorMessage()) {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        }
    }
}
