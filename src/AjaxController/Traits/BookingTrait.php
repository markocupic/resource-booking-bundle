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
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

trait BookingTrait
{
    protected HtmlSanitizerInterface $htmlSanitizer;

    protected SlotFactory $slotFactory;

    #[Required]
    public function setHtmlSanitizer(HtmlSanitizerInterface $htmlSanitizer): void
    {
        $this->htmlSanitizer = $htmlSanitizer;
    }

    #[Required]
    public function setSlotFactory(SlotFactory $slotFactory): void
    {
        $this->slotFactory = $slotFactory;
    }

    /**
     * @throws \Exception
     */
    protected function getSlotCollectionFromRequest(Request $request, int $bookingRepeatStopWeekTstamp): SlotCollection|null
    {
        $arrSlotCollection = [];
        $resource = $this->getActiveResource();
        $itemsBooked = empty((int) $request->request->get('itemsBooked')) ? 1 : (int) $request->request->get('itemsBooked');
        // Consider input encoding!!!
        $description = (string) $this->framework->getAdapter(Input::class)->post('bookingDescription');
        $description = $this->htmlSanitizer->sanitize($description); // Remove malicious code
        // $request->request->get('bookingDateSelection') won't work, because
        // Symfony doesn't allow non-scalar values in the input bag (design change since Symfony 6)
        $arrDateSelection = $request->request->all()['bookingDateSelection'] ?? null;

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
                    $bookingRepeatStopWeekTstamp,
                );

                $slot->setTimeSlotId($timeSlotId);

                $arrSlotCollection[] = $slot;

                // Handle repetitions
                if ($endTime < $bookingRepeatStopWeekTstamp) {
                    $doRepeat = true;

                    while (true === $doRepeat) {
                        $startTime = $this->framework->getAdapter(DateHelper::class)->addDaysToTime(7, $startTime);
                        $endTime = $this->framework->getAdapter(DateHelper::class)->addDaysToTime(7, $endTime);

                        /** @var SlotMain $slot */
                        $slot = $this->slotFactory->get(
                            $timeSlotId,
                            SlotBooking::MODE,
                            $resource,
                            $startTime,
                            $endTime,
                            $itemsBooked,
                            $bookingRepeatStopWeekTstamp,
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

        $dca = $this->loadDcaForTable('tl_resource_booking');

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

        $keys = array_keys($request->request->all());

        foreach ($keys as $k) {
            if (!isset($arrUserInput[$k])) {
                $blnDecode = ($dca['fields'][$k]['eval']['decodeEntities'] ?? false) === true;
                // Consider input encoding!!!
                $value = $this->framework->getAdapter(Input::class)->post($k, $blnDecode);
                $arrUserInput[$k] = \is_string($value) ? $this->htmlSanitizer->sanitize($value) : $value;
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

            $arrUserInput['title'] = \sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->getActiveResource()->title,
                $this->translator->trans('MSC.bookedBy', [], 'contao_default'),
                $this->user->getLoggedInUser()->firstname,
                $this->user->getLoggedInUser()->lastname,
                $this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('datimFormat'), $slot->startTime),
                $this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('datimFormat'), $slot->endTime),
            );

            $slotCollection->current()->setBookingData($arrUserInput);
        }

        return $slotCollection;
    }

    private function loadDcaForTable(string $tableName): array
    {
        $this->framework
            ->getAdapter(Controller::class)
            ->loadDataContainer($tableName)
        ;

        return $GLOBALS['TL_DCA'][$tableName];
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
                if (!$slot->isWithinAllowedDateRange()) {
                    $this->setErrorMessage('RBB.ERR.invalidStartOrEndTime');
                } elseif ($slot->isFullyBooked()) {
                    $this->setErrorMessage('RBB.ERR.resourceIsAlreadyFullyBooked');
                } elseif ($slot->isBlocked()) {
                    $this->setErrorMessage('RBB.ERR.slotIsBlocked');
                } elseif (!$slot->isBookable()) {
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
}
