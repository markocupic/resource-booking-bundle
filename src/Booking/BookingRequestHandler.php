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

namespace Markocupic\ResourceBookingBundle\Booking;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Input;
use Contao\ModuleModel;
use Markocupic\ResourceBookingBundle\Api\Input\BookingInput;
use Markocupic\ResourceBookingBundle\AppState\AppConfigResolver;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Slot\Slot;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\CsvList;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns a booking request (BookingInput) into slots: the selected slots, their weekly
 * repetitions and the data of the bookings to save. Used by the booking preview
 * (BookingFormValidationController) and the booking (BookingController).
 *
 * Saving the bookings is up to the BookingController (transaction, lock, events).
 */
class BookingRequestHandler
{
    /**
     * Upper limit of weekly repetitions per selected time slot (safety net, about 10 years).
     */
    public const MAX_REPETITIONS = 520;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly LoggedInFrontendUser $loggedInUser,
        private readonly SlotFactory $slotFactory,
        private readonly WeekBoundaries $weekBoundaries,
        private readonly AppConfigResolver $appConfigResolver,
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Returns true if the resource belongs to one of the resource types selected
     * in the module (tl_module.resourceBooking_resourceTypes).
     */
    public static function isResourceOfModule(ResourceBookingResourceModel $resource, ModuleModel $moduleModel): bool
    {
        $resourceTypeIds = CsvList::parseIds($moduleModel->resourceBooking_resourceTypes);

        return \in_array((int) $resource->pid, $resourceTypeIds, true);
    }

    /**
     * The resource of a booking request: it must be published and belong to a
     * resource type of the module (protection against manipulated requests).
     *
     * @throws \Exception
     */
    public function getResource(BookingInput $input, AppState $appState): ResourceBookingResourceModel
    {
        $resource = $this->framework
            ->getAdapter(ResourceBookingResourceModel::class)
            ->findPublishedByPk($input->resourceId)
        ;

        if (null === $resource) {
            throw new \Exception(\sprintf('Resource with Id %s not found.', $input->resourceId));
        }

        if (!self::isResourceOfModule($resource, $appState->module)) {
            throw new \Exception(\sprintf('Resource with Id %s is not available in module with Id %s.', $resource->id, $appState->getModuleId()));
        }

        return $resource;
    }

    /**
     * Creates the slots of the booking request (selected slots and their weekly
     * repetitions, sorted by start time) including the data of the bookings to save.
     *
     * @throws \Exception
     */
    public function createSlots(BookingInput $input, ResourceBookingResourceModel $resource, AppState $appState, string $bookingUuid): SlotCollection
    {
        // A manipulated request must not be able to create an unlimited number of repetitions:
        // repeat at most up to the last week, in which the resource can be booked.
        $repeatUntil = $this->limitRepeatUntil($input->repeatUntil, $resource);
        $slots = [];

        foreach ($input->slots as $selectedSlot) {
            $startTime = $selectedSlot->startTime;
            $endTime = $selectedSlot->endTime;

            $slots[] = $this->slotFactory->create($selectedSlot->timeSlotId, $resource, $startTime, $endTime, $input->requestedItems, $repeatUntil);

            // Weekly repetitions
            for ($repetitions = 1; $endTime < $repeatUntil && $repetitions <= self::MAX_REPETITIONS; ++$repetitions) {
                $startTime = DateHelper::addDays(7, $startTime);
                $endTime = DateHelper::addDays(7, $endTime);

                $slot = $this->slotFactory->create($selectedSlot->timeSlotId, $resource, $startTime, $endTime, $input->requestedItems, $repeatUntil);
                $slots[] = $slot;

                if ($slot->weekStart >= $repeatUntil) {
                    break;
                }
            }
        }

        $bookingData = $this->getBookingData($input, $resource, $appState, $bookingUuid);
        $member = $this->loggedInUser->getLoggedInUser();
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $datimFormat = (string) $this->framework->getAdapter(Config::class)->get('datimFormat');

        return (new SlotCollection(...$slots))
            ->sortedByStartTime()
            ->map(
                fn (Slot $slot): Slot => $slot->withBookingData([
                    ...$bookingData,
                    'timeSlotId' => $slot->timeSlotId,
                    'startTime' => $slot->startTime,
                    'endTime' => $slot->endTime,
                    'title' => \sprintf(
                        '%s : %s %s %s [%s - %s]',
                        $resource->title,
                        $this->translator->trans('MSC.bookedBy', [], 'contao_default'),
                        $member?->firstname,
                        $member?->lastname,
                        $dateAdapter->parse($datimFormat, $slot->startTime),
                        $dateAdapter->parse($datimFormat, $slot->endTime),
                    ),
                ]),
            )
        ;
    }

    /**
     * Returns the translation key of the reason why the slots cannot be booked,
     * or null if all slots can be booked.
     */
    public function getErrorKey(SlotCollection $slots): string|null
    {
        if ($slots->isEmpty()) {
            return 'RBB.ERR.selectBookingDatesPlease';
        }

        foreach ($slots as $slot) {
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
     * Data of every booking of the request (row of tl_resource_booking without the
     * slot specific fields). The custom form fields cannot replace fields of the bundle.
     *
     * @return array<string, mixed>
     */
    private function getBookingData(BookingInput $input, ResourceBookingResourceModel $resource, AppState $appState, string $bookingUuid): array
    {
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $data = [
            'member' => $this->loggedInUser->getLoggedInUser()?->id,
            'itemsBooked' => $input->requestedItems,
            'tstamp' => $this->clock->now()->getTimestamp(),
            'pid' => $resource->id,
            'moduleId' => $appState->getModuleId(),
            'bookingSource' => BookingSource::WEEK_CALENDAR,
            'bookingUuid' => $bookingUuid,
            // Contao input handling: tags are stripped and special characters are encoded (input encoding).
            // Do not use the Symfony HTML sanitizer for these plain text values: it encodes characters
            // like "@" and "+" as HTML entities, e.g. an e-mail address "a&#64;b.ch" or "&#43;41 …".
            'description' => (string) $inputAdapter->post('bookingDescription'),
        ];

        $dca = $this->loadDca('tl_resource_booking');

        foreach (array_keys($input->formFields) as $field) {
            if (\array_key_exists($field, $data)) {
                continue;
            }

            // Contao input handling (see above), fields with "decodeEntities" are not encoded
            $decodeEntities = true === ($dca['fields'][$field]['eval']['decodeEntities'] ?? false);
            $data[$field] = $inputAdapter->post($field, $decodeEntities);
        }

        return $data;
    }

    /**
     * The booking can be repeated at most up to the last week, in which the
     * resource can be booked (weeksAhead, bookableUntil).
     *
     * @throws \Exception
     */
    private function limitRepeatUntil(int $repeatUntil, ResourceBookingResourceModel $resource): int
    {
        $lastPermittedWeek = $this->weekBoundaries->getLastPermittedWeek(
            $this->appConfigResolver->getBeginWeek($resource),
            $resource,
            $this->clock->now()->getTimestamp(),
        );

        return min($repeatUntil, $lastPermittedWeek);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDca(string $table): array
    {
        $this->framework->getAdapter(Controller::class)->loadDataContainer($table);

        return $GLOBALS['TL_DCA'][$table] ?? [];
    }
}
