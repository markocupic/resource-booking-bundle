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

namespace Markocupic\ResourceBookingBundle\AjaxController\Traits;

use Contao\Config;
use Contao\Controller;
use Contao\Date;
use Contao\Input;
use Markocupic\ResourceBookingBundle\Slot\SlotBooking;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Ramsey\Uuid\Uuid;

trait BookingTrait
{
    /**
     * @throws \Exception
     */
    protected function getSlotCollectionFromRequest(int $bookingRepeatStopWeekTstamp): SlotCollection|null
    {
        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $arrSlotCollection = [];
        $resource = $this->getActiveResource();
        $itemsBooked = empty($inputAdapter->post('itemsBooked')) ? 1 : (int) $inputAdapter->post('itemsBooked');
        $description = (string) $inputAdapter->post('bookingDescription');
        // $request->request->get('bookingDateSelection') won't work, because
        // Symfony doesn't allow non-scalar values in the input bag (design change since Symfony 6)
        $arrDateSelection = $inputAdapter->post('bookingDateSelection');

        if (!empty($arrDateSelection) && \is_array($arrDateSelection)) {
            foreach ($arrDateSelection as $strTimeSlot) {
                // slotId-startTime-endTime-beginnWeekTimestampSelectedWeek
                $arrTimeSlot = explode('-', $strTimeSlot);

                $timeSlotId = (int) $arrTimeSlot[0];
                $startTime = (int) $arrTimeSlot[1];
                $endTime = (int) $arrTimeSlot[2];

                /** @var SlotBooking $slot */
                $slot = $this->slotFactory->get(
                    $timeSlotId,
                    SlotBooking::MODE,
                    $resource,
                    $startTime,
                    $endTime,
                    $itemsBooked,
                    $bookingRepeatStopWeekTstamp
                );

                $slot->setTimeSlotId($timeSlotId);

                $arrSlotCollection[] = $slot;

                // Handle repetitions
                if ($endTime < $bookingRepeatStopWeekTstamp) {
                    $doRepeat = true;

                    while (true === $doRepeat) {
                        $startTime = $dateHelperAdapter->addDaysToTime(7, $startTime);
                        $endTime = $dateHelperAdapter->addDaysToTime(7, $endTime);

                        /** @var SlotMain $slot */
                        $slot = $this->slotFactory->get(
                            $timeSlotId,
                            SlotBooking::MODE,
                            $resource,
                            $startTime,
                            $endTime,
                            $itemsBooked,
                            $bookingRepeatStopWeekTstamp
                        );

                        $arrSlotCollection[] = $slot;

                        // Stop repeating
                        if ($slot->beginnWeekTimestampSelectedWeek >= $bookingRepeatStopWeekTstamp) {
                            $doRepeat = false;
                        }
                    }
                }
            }
        }

        $slotCollection = (new SlotCollection($arrSlotCollection))->sortBy('startTime');

        // Load data container
        $this->framework
            ->getAdapter(Controller::class)
            ->loadDataContainer('tl_resource_booking')
        ;

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
        // Custom form fields must be registered in the bundle configuration
        // @See: BookingController::validateInputs()
        foreach ($inputAdapter->getKeys() as $k) {
            if (!isset($arrUserInput[$k])) {
                $blnDecode = isset($dca['fields'][$k]['eval']['decodeEntities']) && true === $dca['fields'][$k]['eval']['decodeEntities'];
                $arrUserInput[$k] = $blnDecode ? $inputAdapter->post($k, true) : $inputAdapter->post($k);
            }
        }

        $slotCollection->reset();

        $bookingUuid = $this->getBookingUuid();

        while ($slotCollection->next()) {
            $slot = $slotCollection->current();
            $arrUserInput['bookingUuid'] = $bookingUuid;
            $arrUserInput['timeSlotId'] = $slot->timeSlotId;
            $arrUserInput['startTime'] = $slot->startTime;
            $arrUserInput['endTime'] = $slot->endTime;

            $arrUserInput['title'] = sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->getActiveResource()->title,
                $this->translator->trans('MSC.bookedBy', [], 'contao_default'),
                $this->user->getLoggedInUser()->firstname,
                $this->user->getLoggedInUser()->lastname,
                $dateAdapter->parse($configAdapter->get('datimFormat'), $slot->startTime),
                $dateAdapter->parse($configAdapter->get('datimFormat'), $slot->endTime)
            );

            $slotCollection->current()->setBookingData($arrUserInput);
        }

        return $slotCollection;
    }

    private function getBookingUuid(): string
    {
        if (!$this->bookingUuid) {
            $this->bookingUuid = Uuid::uuid4()->toString();
        }

        return $this->bookingUuid;
    }

    /**
     * @throws \Exception
     */
    private function isBookingPossible(SlotCollection|null $slotCollection): bool
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
                if (!$slot->isDateInPermittedRange()) {
                    $this->setErrorMessage('RBB.ERR.invalidStartOrEndTime');
                } elseif ($slot->isFullyBooked()) {
                    $this->setErrorMessage('RBB.ERR.resourceIsAlreadyFullyBooked');
                } elseif (!$slot->isBookable()) {
                    $this->setErrorMessage('RBB.ERR.notEnoughItemsAvailable');
                } elseif (!$slot->isBlocked()) {
                    $this->setErrorMessage('RBB.ERR.slotIsBlocked');
                } else {
                    // This case normally should not happen
                    $this->setErrorMessage('RBB.ERR.slotNotBookable');
                }

                return false;
            }
        }

        return true;
    }
}
