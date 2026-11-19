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

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\ModuleModel;
use Contao\System;
use Markocupic\ResourceBookingBundle\Api\Output\CalendarData;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\CsvList;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Data of the week calendar for the front end: filter (resource types, resources,
 * weeks), header, logged-in user, weekdays and the table.
 *
 * Built from the AppState of the request, nothing is stored between requests.
 */
class CalendarDataBuilder
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly LoggedInFrontendUser $user,
        private readonly TranslatorInterface $translator,
        private readonly WeekTableBuilder $weekTableBuilder,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * @throws \Exception
     */
    public function build(AppState $state, AjaxResponse $ajaxResponse): CalendarData
    {
        $configAdapter = $this->framework->getAdapter(Config::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());

        // Show message if no resource type is selected.
        if (null === $state->resourceType) {
            $ajaxResponse->setInfoMessage($this->translator->trans('RBB.MSG.selectResourceTypePlease', [], 'contao_default'));
        }

        // Show message if a resource type is selected but no resource is selected.
        if (null !== $state->resourceType && null === $state->resource) {
            $ajaxResponse->setInfoMessage($this->translator->trans('RBB.MSG.selectResourcePlease', [], 'contao_default'));
        }

        $dateFormat = (string) $configAdapter->get('dateFormat');
        $weekEnd = DateHelper::addDays(6, $state->activeWeekStart);
        $weekdays = $this->getWeekdays($state, $state->activeWeekStart, $state->resource);
        $weekNumber = $dateAdapter->parse('W', $state->activeWeekStart);

        // Table of the week calendar (rows with one slot per displayed weekday) and the time slots of the first column
        $rows = [];
        $timeSlots = [];

        if (null !== $state->resource && null !== $state->resourceType) {
            // All seven days of the week, starting with the first day of the week of the timetable
            $daysOfWeek = $this->getDaysOfWeek($state);

            $rows = $this->weekTableBuilder->buildRows($daysOfWeek, $state->activeWeekStart, $state->module, $state->resource, $this->user->getLoggedInUser());
            $timeSlots = $this->weekTableBuilder->buildTimeSlots($state->resource, $daysOfWeek);
        }

        return new CalendarData(
            resourceTypes: $this->getResourceTypeSelectOptions($state->module),
            resources: $this->getResourceSelectOptions($state->resourceType),
            jumpPrevWeek: $this->getJumpWeekDate(-1, $state),
            jumpNextWeek: $this->getJumpWeekDate(1, $state),
            weekSelection: $this->getWeekSelection($state, $state->firstPermittedWeekStart, $state->lastPermittedWeekStart, true),
            firstPermittedWeekStart: $state->firstPermittedWeekStart,
            lastPermittedWeekStart: $state->lastPermittedWeekStart,
            // CalendarData sends only the name of the member to the front end
            member: null === $this->user->getLoggedInUser() ? null : $this->user->getModel()?->row(),
            activeWeekStart: $state->activeWeekStart,
            activeWeek: [
                'tstampStart' => $state->activeWeekStart,
                'tstampEnd' => $weekEnd,
                'dateStart' => $dateAdapter->parse($dateFormat, $state->activeWeekStart),
                'dateEnd' => $dateAdapter->parse($dateFormat, $weekEnd),
                'weekNumber' => $weekNumber,
                'year' => $dateAdapter->parse('Y', $state->activeWeekStart),
                'calWeek' => (int) $weekNumber,
                // Displayed days of the active week for the header, e.g. "Montag, 16. bis Freitag, 20. November 2026"
                'daySpan' => $this->getDaySpan($weekdays, $state->activeWeekStart),
            ],
            bookingRepeatsSelection: $this->getWeekSelection($state, $state->activeWeekStart, $state->lastPermittedWeekStart, false),
            weekdays: $weekdays,
            activeResourceType: $state->resourceType?->row(),
            activeResource: null === $state->resourceType ? null : $state->resource?->row(),
            rows: $rows,
            timeSlots: $timeSlots,
        );
    }

    /**
     * The published resource types of the module.
     *
     * @return list<array<string, mixed>>
     */
    private function getResourceTypeSelectOptions(ModuleModel $module): array
    {
        $resourceTypeIds = CsvList::parseIds($module->resourceBooking_resourceTypes);
        $resourceTypes = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class)->findPublishedByIds($resourceTypeIds);

        return null === $resourceTypes ? [] : array_values($resourceTypes->fetchAll());
    }

    /**
     * The published resources of the resource type.
     *
     * @return list<array<string, mixed>>
     */
    private function getResourceSelectOptions(ResourceBookingResourceTypeModel|null $resourceType): array
    {
        if (null === $resourceType) {
            return [];
        }

        $resources = $this->framework->getAdapter(ResourceBookingResourceModel::class)->findPublishedByPid((int) $resourceType->id);

        return null === $resources ? [] : array_values($resources->fetchAll());
    }

    /**
     * Link to the previous (-1) or next (1) week; disabled outside the permitted weeks
     * or without an active resource.
     *
     * @return array{disabled: bool, tstamp: int}
     */
    private function getJumpWeekDate(int $weeks, AppState $state): array
    {
        $jumpTime = DateHelper::addDays(7 * $weeks, $state->activeWeekStart);
        $disabled = false;

        // The permitted weeks depend on the active resource
        if ($jumpTime < $state->firstPermittedWeekStart || $jumpTime > $state->lastPermittedWeekStart) {
            $jumpTime = $state->activeWeekStart;
            $disabled = true;
        }

        if ($state->activeWeekStart <= 0 || null === $state->resourceType || null === $state->resource) {
            $disabled = true;
        }

        return ['disabled' => $disabled, 'tstamp' => $jumpTime];
    }

    /**
     * Options of the week selection from the first to the last week (first days of the weeks).
     *
     * @return list<array<string, mixed>>
     */
    private function getWeekSelection(AppState $state, int $firstWeek, int $lastWeek, bool $injectEmptyLine = false): array
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $this->framework->getAdapter(System::class)->loadLanguageFile('default', $this->translator->getLocale());

        $currentWeek = DateHelper::getFirstDayOfWeek($state->getBeginWeek(), $this->clock->now()->getTimestamp());
        $weeks = [];

        for ($weekStart = $firstWeek; $weekStart <= $lastWeek; $weekStart = DateHelper::addDays(7, $weekStart)) {
            $cssClass = match (true) {
                $weekStart === $currentWeek => 'current-week',
                $weekStart > $currentWeek => 'future-week',
                default => 'past-week',
            };

            // Separator before the current week
            if ($injectEmptyLine && $weekStart === $currentWeek) {
                $weeks[] = ['tstamp' => '', 'date' => '', 'optionText' => '-------------'];
            }

            $dateStart = $dateAdapter->parse('d.m.Y', $weekStart);
            $weekEnd = strtotime($dateStart.' + 6 days');
            $dateEnd = $dateAdapter->parse('d.m.Y', $weekEnd);
            $weekNumber = $dateAdapter->parse('W', $weekStart);
            $year = $dateAdapter->parse('Y', $weekStart);

            $weeks[] = [
                'cssClass' => $cssClass,
                'tstamp' => $weekStart,
                'tstampBeginWeek' => $weekStart,
                'tstampEndWeek' => (int) $weekEnd,
                'stringBeginWeek' => $dateStart,
                'stringEndWeek' => $dateEnd,
                'daySpan' => $dateStart.' - '.$dateEnd,
                'calWeek' => (int) $weekNumber,
                'year' => $year,
                'optionDateStart' => $dateStart,
                'optionDateEnd' => $dateEnd,
                'optionText' => $this->translator->trans('MSC.weekSelectOptionText', [$weekNumber, $year, $dateStart, $dateEnd], 'contao_default'),
            ];
        }

        return $weeks;
    }

    /**
     * The displayed weekdays of the active week (header of the table): weekdays on
     * which none of the time slots of the resource is available are skipped.
     *
     * @return list<array<string, mixed>>
     */
    private function getWeekdays(AppState $state, int $activeWeekStart, ResourceBookingResourceModel|null $resource = null): array
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $daysOfWeek = $this->getDaysOfWeek($state);
        $visibleWeekdays = $this->weekTableBuilder->getVisibleWeekdaysOfResource($resource, $daysOfWeek);
        $weekdays = [];

        foreach ($daysOfWeek as $index => $weekday) {
            if (!\in_array($weekday, $visibleWeekdays, true)) {
                continue;
            }

            $day = strtotime($dateAdapter->parse('Y-m-d', $activeWeekStart).' +'.$index.' day');

            $weekdays[] = [
                'index' => $index,
                'tstamp' => $day,
                'name' => $weekday,
                'title' => $this->translator->trans('MSC.DAYS_LONG.'.$weekday, [], 'contao_default'),
                'titleShort' => $this->translator->trans('MSC.DAYS_SHORTENED.'.$weekday, [], 'contao_default'),
                'date' => $dateAdapter->parse('d.m.Y', $day),
            ];
        }

        return $weekdays;
    }

    /**
     * Returns the displayed days of the week as text, e.g.
     * "Montag, 16. bis Freitag, 20. November 2026". The month and the year
     * are only repeated if they differ. The date formats and the separator
     * can be changed in the language files (RBB.daySpan*).
     *
     * @param array<int, array{tstamp: int}> $weekdays
     */
    private function getDaySpan(array $weekdays, int $activeWeekStart): string
    {
        if ($activeWeekStart <= 0) {
            return '';
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);

        if (empty($weekdays)) {
            $start = $activeWeekStart;
            $end = strtotime($dateAdapter->parse('Y-m-d', $activeWeekStart).' +6 days');
        } else {
            $start = (int) reset($weekdays)['tstamp'];
            $end = (int) end($weekdays)['tstamp'];
        }

        $formatFull = $this->translator->trans('RBB.daySpanFormatFull', [], 'contao_default');

        if ($dateAdapter->parse('Y-m-d', $start) === $dateAdapter->parse('Y-m-d', $end)) {
            return $dateAdapter->parse($formatFull, $start);
        }

        $formatStart = match (true) {
            $dateAdapter->parse('Y', $start) !== $dateAdapter->parse('Y', $end) => $formatFull,
            $dateAdapter->parse('m', $start) !== $dateAdapter->parse('m', $end) => $this->translator->trans('RBB.daySpanFormatDayMonth', [], 'contao_default'),
            default => $this->translator->trans('RBB.daySpanFormatDay', [], 'contao_default'),
        };

        return $this->translator->trans('RBB.daySpan', [$dateAdapter->parse($formatStart, $start), $dateAdapter->parse($formatFull, $end)], 'contao_default');
    }

    /**
     * All seven weekdays, starting with the first day of the week of the timetable.
     *
     * @return list<string>
     */
    private function getDaysOfWeek(AppState $state): array
    {
        $weekdays = [...RbbConfig::RBB_WEEKDAYS, ...RbbConfig::RBB_WEEKDAYS];
        $offset = (int) array_search($state->getBeginWeek(), $weekdays, true);

        return \array_slice($weekdays, $offset, 7);
    }
}
