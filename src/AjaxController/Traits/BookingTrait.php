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
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Booking\BookingSource;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Slot\Slot;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared by the booking and the booking form validation controller.
 *
 * The using class provides (constructor injection): $framework, $user, $utils,
 * $translator, $slotFactory and $weekBoundaries. The trait does
 * not keep any state: everything is passed as an argument or returned.
 */
trait BookingTrait
{
    /**
     * Upper limit of weekly repetitions per selected time slot (safety net, about 10 years).
     */
    public const MAX_REPETITIONS = 520;

    /**
     * Creates the slots of the booking request (selected slots and their repetitions)
     * including the booking data to be saved.
     *
     * @throws \Exception
     */
    protected function getSlotCollectionFromRequest(Request $request, ResourceBookingResourceModel $resource, AppState $appState, int $bookingRepeatStopWeekTstamp, string $bookingUuid): SlotCollection
    {
        $arrSlotCollection = [];
        // Anything that is not a positive integer becomes 0 and is rejected by the slot (see Slot::hasValidItemCount())
        $itemsBooked = BookingRequestValidator::parseItemCount($request->request->all()['itemsBooked'] ?? null);
        // Contao input handling: tags are stripped and special characters are encoded (input encoding).
        // Do not use the Symfony HTML sanitizer for these plain text values: it encodes characters
        // like "@" and "+" as HTML entities, e.g. an e-mail address "a&#64;b.ch" or "&#43;41 …".
        $description = (string) $this->framework->getAdapter(Input::class)->post('bookingDescription');
        // $request->request->get('bookingDateSelection') won't work, because
        // Symfony doesn't allow non-scalar values in the input bag (design change since Symfony 6)
        $selectedSlots = BookingRequestValidator::parseSlotSelection($request->request->all()['bookingDateSelection'] ?? null);

        // A manipulated request must not be able to create an unlimited number of repetitions:
        // repeat at most up to the last week, in which the resource can be booked.
        $bookingRepeatStopWeekTstamp = $this->limitBookingRepeatStopWeek($bookingRepeatStopWeekTstamp, $resource);

        foreach ($selectedSlots as ['timeSlotId' => $timeSlotId, 'startTime' => $startTime, 'endTime' => $endTime]) {
            $slot = $this->slotFactory->create(
                $timeSlotId,
                $resource,
                $startTime,
                $endTime,
                $itemsBooked,
                $bookingRepeatStopWeekTstamp,
            );

            $arrSlotCollection[] = $slot;

            // Handle repetitions
            $repetitions = 0;

            while ($endTime < $bookingRepeatStopWeekTstamp && $repetitions < self::MAX_REPETITIONS) {
                ++$repetitions;
                $startTime = $this->framework->getAdapter(DateHelper::class)->addDaysToTime(7, $startTime);
                $endTime = $this->framework->getAdapter(DateHelper::class)->addDaysToTime(7, $endTime);

                $slot = $this->slotFactory->create(
                    $timeSlotId,
                    $resource,
                    $startTime,
                    $endTime,
                    $itemsBooked,
                    $bookingRepeatStopWeekTstamp,
                );

                $arrSlotCollection[] = $slot;

                // Stop repeating
                if ($slot->weekStart >= $bookingRepeatStopWeekTstamp) {
                    break;
                }
            }
        }

        $slotCollection = (new SlotCollection(...$arrSlotCollection))->sortedByStartTime();

        $dca = $this->loadDcaForTable('tl_resource_booking');
        $member = $this->user->getLoggedInUser();

        $arrUserInput = [
            'member' => $member?->id,
            'itemsBooked' => $itemsBooked,
            'tstamp' => time(),
            'pid' => $resource->id,
            'moduleId' => $appState->getModuleId(),
            'bookingSource' => BookingSource::WEEK_CALENDAR,
            'description' => $description,
        ];

        // Add data from POST, thus the extension can easily be extended
        // Custom form fields must be registered in the bundle configuration
        // @See: BookingController::validateInputs()

        $keys = array_keys($request->request->all());

        foreach ($keys as $k) {
            // Fields like confirmed or member are set by the server only
            if (\in_array($k, BookingRequestValidator::PROTECTED_BOOKING_FIELDS, true)) {
                continue;
            }

            if (!isset($arrUserInput[$k])) {
                $blnDecode = ($dca['fields'][$k]['eval']['decodeEntities'] ?? false) === true;
                // Contao input handling (see above), fields with "decodeEntities" are not encoded
                $arrUserInput[$k] = $this->framework->getAdapter(Input::class)->post($k, $blnDecode);
            }
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $datimFormat = $this->framework->getAdapter(Config::class)->get('datimFormat');

        return $slotCollection->map(
            function (Slot $slot) use ($arrUserInput, $bookingUuid, $resource, $member, $dateAdapter, $datimFormat): Slot {
                $arrUserInput['bookingUuid'] = $bookingUuid;
                $arrUserInput['timeSlotId'] = $slot->timeSlotId;
                $arrUserInput['startTime'] = $slot->startTime;
                $arrUserInput['endTime'] = $slot->endTime;

                $arrUserInput['title'] = \sprintf(
                    '%s : %s %s %s [%s - %s]',
                    $resource->title,
                    $this->translator->trans('MSC.bookedBy', [], 'contao_default'),
                    $member?->firstname,
                    $member?->lastname,
                    $dateAdapter->parse($datimFormat, $slot->startTime),
                    $dateAdapter->parse($datimFormat, $slot->endTime),
                );

                return $slot->withBookingData($arrUserInput);
            },
        );
    }

    /**
     * Returns the translation key of the reason why the slots cannot be booked,
     * or null if all slots can be booked.
     *
     * @throws \Exception
     */
    protected function getBookingErrorKey(SlotCollection $slotCollection): string|null
    {
        if ($slotCollection->isEmpty()) {
            return 'RBB.ERR.selectBookingDatesPlease';
        }

        foreach ($slotCollection as $slot) {
            if ($slot->isBookable()) {
                continue;
            }

            return match (true) {
                !$slot->isValidTimeSlot => 'RBB.ERR.invalidTimeSlot',
                !$slot->hasValidItemCount() => 'RBB.ERR.invalidItemsBooked',
                $slot->isOutsideBookingPeriod() => 'RBB.ERR.outsideBookingPeriod',
                !$slot->isInBookingWindow() => 'RBB.ERR.invalidStartOrEndTime',
                !$slot->isAvailableOnWeekday() => 'RBB.ERR.timeSlotNotAvailableOnWeekday',
                $slot->isInBlockedPeriod() => 'RBB.ERR.slotInBlockedPeriod',
                $slot->isFullyBooked() => 'RBB.ERR.resourceIsAlreadyFullyBooked',
                default => 'RBB.ERR.notEnoughItemsAvailable',
            };
        }

        return null;
    }

    /**
     * The booking can be repeated at most up to the last week, in which the
     * resource can be booked (weeksAhead, bookableUntil).
     *
     * @throws \Exception
     */
    private function limitBookingRepeatStopWeek(int $bookingRepeatStopWeekTstamp, ResourceBookingResourceModel $resource): int
    {
        $lastPermittedWeek = $this->weekBoundaries->getLastPermittedWeek(
            ['beginWeek' => $this->utils->getBeginWeek($resource)],
            $resource,
            time(),
        );

        return min($bookingRepeatStopWeekTstamp, $lastPermittedWeek);
    }

    private function loadDcaForTable(string $tableName): array
    {
        $this->framework
            ->getAdapter(Controller::class)
            ->loadDataContainer($tableName)
        ;

        return $GLOBALS['TL_DCA'][$tableName];
    }
}
