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

namespace Markocupic\ResourceBookingBundle\WeekCalendar;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Restriction\TimeSlotRules;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;

/**
 * Builds the table of the week calendar: one row per time slot, one cell (slot)
 * per displayed weekday, plus the time slots of the first column.
 *
 * Only the timetable decides which weekdays (columns) and time slots (rows) are
 * displayed: a weekday is displayed if at least one time slot is available on it,
 * a time slot if it is available on at least one displayed weekday. Blocked
 * periods do not hide anything, their slots are displayed as blocked.
 */
class WeekTableBuilder
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SlotFactory $slotFactory,
        private readonly BookingPresenter $bookingPresenter,
        private readonly SlotPresenter $slotPresenter,
        private readonly DisplayedFields $displayedFields,
    ) {
    }

    /**
     * Returns the rows of the week calendar.
     *
     * @param list<string> $daysOfWeek All seven weekdays, starting with the first day of the week (e.g. "monday")
     *
     * @return list<array{cellData: list<array<string, mixed>>, rowData: \stdClass}>
     *
     * @throws \Exception
     */
    public function buildRows(array $daysOfWeek, int $activeWeekStart, ModuleModel $moduleModel, ResourceBookingResourceModel $resource, FrontendUser|null $user = null): array
    {
        $timeSlots = $this->getPublishedTimeSlots($resource);
        $visibleWeekdays = self::getVisibleWeekdays($timeSlots, $daysOfWeek);

        // Fields that may be sent to the front end (module settings and bundle configuration)
        $memberFields = $this->displayedFields->getMemberFields($moduleModel);
        $bookingFields = $this->displayedFields->getBookingFields($moduleModel);

        // Fetch the bookings of the whole week with one query instead of one query per cell.
        // A day is added as a margin for time slots ending after midnight.
        $this->slotFactory->preloadBookings((int) $resource->id, $activeWeekStart, strtotime('+8 days', $activeWeekStart));

        $rows = [];

        foreach ($timeSlots as $timeSlot) {
            // Skip time slots that are not available on any of the displayed weekdays
            if (!self::isTimeSlotDisplayed($timeSlot, $visibleWeekdays)) {
                continue;
            }

            $rowIndex = \count($rows);
            $cells = [];

            foreach ($daysOfWeek as $dayIndex => $weekday) {
                // Skip weekdays that are not displayed in the week calendar
                if (!\in_array($weekday, $visibleWeekdays, true)) {
                    continue;
                }

                $cells[] = $this->buildCell($timeSlot, $dayIndex, $rowIndex, $activeWeekStart, $moduleModel, $resource, $user, $memberFields, $bookingFields);
            }

            $rows[] = ['cellData' => $cells, 'rowData' => self::getRowAttributes((int) $moduleModel->id, $timeSlot)];
        }

        return $rows;
    }

    /**
     * Returns the time slots of the first column of the week calendar.
     *
     * @param list<string> $daysOfWeek
     *
     * @return list<\stdClass>
     */
    public function buildTimeSlots(ResourceBookingResourceModel $resource, array $daysOfWeek): array
    {
        $utcTimeHelperAdapter = $this->framework->getAdapter(UtcTimeHelper::class);

        $timeSlots = $this->getPublishedTimeSlots($resource);
        $visibleWeekdays = self::getVisibleWeekdays($timeSlots, $daysOfWeek);
        $result = [];

        foreach ($timeSlots as $timeSlot) {
            // The same rows are skipped in buildRows()
            if (!self::isTimeSlotDisplayed($timeSlot, $visibleWeekdays)) {
                continue;
            }

            $cssId = StringUtil::deserialize($timeSlot['cssID'] ?? null, true);

            $startTimeFormatted = $utcTimeHelperAdapter->parseStartTime($timeSlot['startTime']);
            $endTimeFormatted = $utcTimeHelperAdapter->parseEndTime($timeSlot['endTime']);
            $endTimeFormatted = '00:00' === $endTimeFormatted ? '24:00' : $endTimeFormatted;

            $timetable = new \stdClass();
            $timetable->cssClass = !empty($cssId[1]) ? $cssId[1] : null;
            $timetable->startTimeString = $startTimeFormatted;
            $timetable->startTime = (int) $timeSlot['startTime'];
            $timetable->endTimeString = $endTimeFormatted;
            $timetable->timeSpanString = $startTimeFormatted.' - '.$endTimeFormatted;
            $timetable->endTime = (int) $timeSlot['endTime'];

            $result[] = $timetable;
        }

        return $result;
    }

    /**
     * Returns the weekdays of the resource that are displayed in the week calendar.
     *
     * @param list<string> $daysOfWeek
     *
     * @return list<string>
     */
    public function getVisibleWeekdaysOfResource(ResourceBookingResourceModel|null $resource, array $daysOfWeek): array
    {
        return self::getVisibleWeekdays($this->getPublishedTimeSlots($resource), $daysOfWeek);
    }

    /**
     * Returns the weekdays on which at least one of the time slots is available
     * (tl_resource_booking_time_slot.weekdays). Without any time slot, all
     * weekdays are kept (the table is empty anyway).
     *
     * @param list<array<string, mixed>> $timeSlots
     * @param list<string>               $daysOfWeek
     *
     * @return list<string>
     */
    public static function getVisibleWeekdays(array $timeSlots, array $daysOfWeek): array
    {
        if (empty($timeSlots)) {
            return array_values($daysOfWeek);
        }

        $visibleWeekdays = [];

        foreach ($daysOfWeek as $weekday) {
            foreach ($timeSlots as $timeSlot) {
                if (TimeSlotRules::isAvailableOnWeekday($timeSlot, $weekday)) {
                    $visibleWeekdays[] = $weekday;
                    break;
                }
            }
        }

        return $visibleWeekdays;
    }

    /**
     * Returns false if the time slot is not available on any of the displayed
     * weekdays. Such a time slot is not displayed as a row.
     *
     * @param array<string, mixed> $timeSlot
     * @param list<string>         $visibleWeekdays
     */
    public static function isTimeSlotDisplayed(array $timeSlot, array $visibleWeekdays): bool
    {
        foreach ($visibleWeekdays as $weekday) {
            if (TimeSlotRules::isAvailableOnWeekday($timeSlot, $weekday)) {
                return true;
            }
        }

        return false;
    }

    /**
     * CSS ID and class of a row. The CSS ID can be overridden in the time slot (cssID).
     *
     * @param array<string, mixed> $timeSlot
     */
    public static function getRowAttributes(int $moduleId, array $timeSlot): \stdClass
    {
        $cssId = StringUtil::deserialize($timeSlot['cssID'] ?? null, true);

        $row = new \stdClass();
        $row->cssRowId = !empty($cssId[0]) ? $cssId[0] : \sprintf('timeSlotModId_%s_%s', $moduleId, $timeSlot['id']);
        $row->cssRowClass = 'rbb-time-slot-'.$timeSlot['id'];

        return $row;
    }

    /**
     * ID of the checkbox of a slot in the booking window.
     */
    public static function getBookingCheckboxId(int $moduleId, int $rowIndex, int $dayIndex): string
    {
        return \sprintf('bookingCheckbox_modId_%s_%s_%s', $moduleId, $rowIndex, $dayIndex);
    }

    /**
     * Value of the checkbox of a slot: "timeSlotId-startTime-endTime-beginWeekTimestamp".
     * It is parsed again by BookingRequestValidator::parseSlotSelection().
     */
    public static function getBookingCheckboxValue(int $timeSlotId, int $startTime, int $endTime, int $activeWeekStart): string
    {
        return \sprintf('%s-%s-%s-%s', $timeSlotId, $startTime, $endTime, $activeWeekStart);
    }

    /**
     * @param array<string, mixed> $timeSlot
     * @param list<string>         $memberFields
     * @param list<string>         $bookingFields
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    private function buildCell(array $timeSlot, int $dayIndex, int $rowIndex, int $activeWeekStart, ModuleModel $moduleModel, ResourceBookingResourceModel $resource, FrontendUser|null $user, array $memberFields, array $bookingFields): array
    {
        $dayStart = strtotime(\sprintf('+%s day', $dayIndex), $activeWeekStart);
        $startTime = $dayStart + (int) $timeSlot['startTime'];
        $endTime = $dayStart + (int) $timeSlot['endTime'];

        $slot = $this->slotFactory->create((int) $timeSlot['id'], $resource, $startTime, $endTime);

        $cell = [
            ...$this->slotPresenter->present($slot),
            'index' => $dayIndex,
            'bookingCheckboxId' => self::getBookingCheckboxId((int) $moduleModel->id, $rowIndex, $dayIndex),
            'bookingCheckboxValue' => self::getBookingCheckboxValue((int) $timeSlot['id'], $startTime, $endTime, $activeWeekStart),
        ];

        // Only explicitly permitted data of the bookings is sent to the front end
        if ($slot->hasBookings()) {
            $isCancelable = $slot->isCancelable();
            $loggedInUserId = null === $user ? null : (int) $user->id;
            $cell['bookings'] = [];

            foreach ($slot->bookings as $booking) {
                $member = $this->framework->getAdapter(MemberModel::class)->findById($booking['member']);

                $cell['bookings'][] = $this->bookingPresenter->present($booking, $member?->row(), $loggedInUserId, $memberFields, $bookingFields, $isCancelable);
            }
        }

        return $cell;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPublishedTimeSlots(ResourceBookingResourceModel|null $resource): array
    {
        if (null === $resource) {
            return [];
        }

        $collection = $this->framework
            ->getAdapter(ResourceBookingTimeSlotModel::class)
            ->findPublishedByPid((int) $resource->timeSlotType)
        ;

        $timeSlots = [];

        if (null !== $collection) {
            foreach ($collection as $timeSlot) {
                $timeSlots[] = $timeSlot->row();
            }
        }

        return $timeSlots;
    }
}
