<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController\Traits;

use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Slot\SlotBooking;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\DateHelper;

/**
 * Trait BookingTrait.
 */
trait BookingTrait
{
    /**
     * @throws \Exception
     */
    private function getSlotCollectionFromRequest(): ?SlotCollection
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $request = $this->requestStack->getCurrentRequest();

        $arrSlotCollection = [];
        $resource = $this->getActiveResource();
        $itemsBooked = (int) $request->request->get('itemsBooked', 1);
        $description = $request->request->has('bookingDescription') ? $stringUtilAdapter->decodeEntities($inputAdapter->post('bookingDescription')) : '';
        $arrDateSelection = $request->request->get('bookingDateSelection', []);
        $this->bookingUuid = $this->getBookingUuid();

        if (!empty($arrDateSelection) && \is_array($arrDateSelection)) {
            foreach ($arrDateSelection as $strTimeSlot) {
                // slotId-startTime-endTime-beginnWeekTimestampSelectedWeek
                $arrTimeSlot = explode('-', $strTimeSlot);

                $timeSlotId = (int) $arrTimeSlot[0];
                $startTime = (int) $arrTimeSlot[1];
                $endTime = (int) $arrTimeSlot[2];

                /** @var SlotMain $slot Create new booking entity */
                $slot = $this->slotFactory->get(
                    SlotBooking::MODE,
                    $resource,
                    $startTime,
                    $endTime,
                    $itemsBooked,
                    (int) $this->bookingRepeatStopWeekTstamp
                );

                $slot->timeSlotId = $timeSlotId;

                $arrSlotCollection[] = $slot;

                // Handle repetitions
                if ($endTime < $this->bookingRepeatStopWeekTstamp) {
                    $doRepeat = true;

                    while (true === $doRepeat) {
                        $startTime = $dateHelperAdapter->addDaysToTime(7, $startTime);
                        $endTime = $dateHelperAdapter->addDaysToTime(7, $endTime);

                        /** @var SlotMain $slot Create new booking entity */
                        $slot = $this->slotFactory->get(
                            SlotBooking::MODE,
                            $resource,
                            $startTime,
                            $endTime,
                            $itemsBooked,
                            (int) $this->bookingRepeatStopWeekTstamp
                        );
                        $slot->timeSlotId = $timeSlotId;

                        $arrSlotCollection[] = $slot;

                        // Stop repeating
                        if ($slot->beginnWeekTimestampSelectedWeek >= $this->bookingRepeatStopWeekTstamp) {
                            $doRepeat = false;
                        }
                    }
                }
            }
        }

        $slotCollection = (new SlotCollection($arrSlotCollection))->sortBy('startTime');

        $controllerAdapter->loadDataContainer('tl_resource_booking');
        $dca = $GLOBALS['TL_DCA']['tl_resource_booking'];

        $arrUserInput = [
            'member' => $this->user->getLoggedInUser()->id,
            'itemsBooked' => $itemsBooked,
            'tstamp' => time(),
            'pid' => $resource->id,
            'moduleId' => $this->sessionBag->get('moduleModelId'),
            'description' => $description,
        ];

        // Add data from POST, thus the extension can easily be extended
        foreach (array_keys($_POST) as $k) {
            if (!isset($arrUserInput[$k])) {
                $arrUserInput[$k] = true === $dca['fields'][$k]['eval']['decodeEntities'] ? $stringUtilAdapter->decodeEntities($inputAdapter->post($k)) : $inputAdapter->post($k);
            }
        }

        $slotCollection->reset();

        while ($slotCollection->next()) {
            $slot = $slotCollection->current();
            $arrUserInput['bookingUuid'] = $this->bookingUuid;
            $arrUserInput['timeSlotId'] = $slot->timeSlotId;
            $arrUserInput['startTime'] = $slot->startTime;
            $arrUserInput['endTime'] = $slot->endTime;

            $arrUserInput['title'] = sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->getActiveResource()->title,
                $this->translator->trans('MSC.bookingFor', [], 'contao_default'),
                $this->user->getLoggedInUser()->firstname,
                $this->user->getLoggedInUser()->lastname,
                $dateAdapter->parse($configAdapter->get('datimFormat'), $slot->startTime),
                $dateAdapter->parse($configAdapter->get('datimFormat'), $slot->endTime)
            );
            $slotCollection->newBooking = $arrUserInput;
        }

        return $slotCollection;
    }

    private function isBookingPossible(?SlotCollection $slotCollection): bool
    {
        if (null === $slotCollection) {
            return false;
        }

        $slotCollection->reset();

        if ($slotCollection->count() < 1) {
            $this->setErrorMessage('RBB.ERR.selectBookingDatesPlease');
            return false;
        }

        while ($slotCollection->next()) {
            /** @var SlotMain $slot */
            $slot = $slotCollection->current();

            if (!$slot->isBookable()) {
                if (!$slot->hasValidDate()) {
                    // Invalid time period
                    $this->setErrorMessage('RBB.ERR.invalidStartOrEndTime');
                } elseif ($slot->isFullyBooked()) {
                    // Resource has already been booked by an other user
                    $this->setErrorMessage('RBB.ERR.resourceIsAlreadyFullyBooked');
                } elseif (!$slot->isBookable()) {
                    // Resource has already been booked by an other user
                    $this->setErrorMessage('RBB.ERR.notEnoughItemsAvailable');
                } else {
                    // This case normally should not happen
                    $this->setErrorMessage('RBB.ERR.slotNotBookable');
                }

                return false;
            }
        }

        return true;
    }

    private function getBookingUuid(): string
    {
        if (!$this->bookingUuid) {
            /** @var StringUtil $stringUtilAdapter */
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

            /** @var Database $databaseAdapter */
            $databaseAdapter = $this->framework->getAdapter(Database::class);

            $this->bookingUuid = $stringUtilAdapter->binToUuid($databaseAdapter->getInstance()->getUuid());
        }

        return $this->bookingUuid;
    }
}
