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
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Str;
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
    ) {
    }

    /**
     * @throws \Exception
     */
    public function build(AppState $state, AjaxResponse $ajaxResponse): array
    {
        $configAdapter = $this->framework->getAdapter(Config::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $strAdapter = $this->framework->getAdapter(Str::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

        $arrData = [];

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

        // Get the module id
        $arrData['moduleId'] = $state->module->id;

        // Filter form: get resource types dropdown
        $arrData['filterBoard']['resourceTypes'] = $this->getResourceTypeSelectOptions($state->module);

        // Filter form: get resource dropdown
        $arrData['filterBoard']['resources'] = $this->getResourceSelectOptions($state->resourceType);

        // Filter form get jump week array
        $arrData['filterBoard']['jumpNextWeek'] = $this->getJumpWeekDate(1, $state);
        $arrData['filterBoard']['jumpPrevWeek'] = $this->getJumpWeekDate(-1, $state);

        // Filter form: get date dropdown
        $arrData['filterBoard']['weekSelection'] = $this->getWeekSelection($state, $state->firstPermittedWeekTstamp, $state->lastPermittedWeekTstamp, true);

        // Logged in user
        $arrData['hasLoggedInUser'] = false;
        $arrData['loggedInUser'] = null;

        if (null !== $this->user->getLoggedInUser()) {
            $arrData['hasLoggedInUser'] = true;
            $arrData['loggedInUser'] = array_map(static fn ($v) => $strAdapter->convertBinUuidsToStringUuids($v), $this->user->getModel()->row());
            $arrData['loggedInUser']['gender'] = !empty($this->user->getModel()->gender) ? $this->translator->trans('MSC.'.$this->user->getModel()->gender, [], 'contao_default') : '';
            unset($arrData['loggedInUser']['password']);
        }

        // Selected week
        $arrData['activeWeekTstamp'] = $state->activeWeekTstamp;
        $arrData['activeWeek'] = [
            'tstampStart' => $state->activeWeekTstamp,
            'tstampEnd' => $dateHelperAdapter->addDaysToTime(6, $state->activeWeekTstamp),
            'dateStart' => $dateAdapter->parse($configAdapter->get('dateFormat'), $state->activeWeekTstamp),
            'dateEnd' => $dateAdapter->parse($configAdapter->get('dateFormat'), $dateHelperAdapter->addDaysToTime(6, $state->activeWeekTstamp)),
            'weekNumber' => $dateAdapter->parse('W', $state->activeWeekTstamp),
            'year' => $dateAdapter->parse('Y', $state->activeWeekTstamp),
        ];

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = $this->getWeekSelection($state, $state->activeWeekTstamp, $state->lastPermittedWeekTstamp, false);

        // Weekdays
        $arrData['weekdays'] = $this->getWeekdays($state, $state->activeWeekTstamp, $state->resource);

        // Displayed days of the active week for the header, e.g. "Montag, 16. bis Freitag, 20. November 2026"
        $arrData['activeWeek']['calWeek'] = (int) $arrData['activeWeek']['weekNumber'];
        $arrData['activeWeek']['daySpan'] = $this->getDaySpan($arrData['weekdays'], $state->activeWeekTstamp);

        // Always send the active resource type and resource, so that the
        // front end does not keep the previous ones after a filter change.
        $arrData['activeResourceType'] = [];
        $arrData['activeResource'] = [];
        $arrData['activeResourceTypeId'] = 'undefined';

        if (null !== $state->resourceType) {
            $arrData['activeResourceType'] = $state->resourceType->row();
            $arrData['activeResourceTypeId'] = $state->resourceType->id;
        }

        // Generate table data
        $arrData['activeResourceId'] = 'undefined';

        if (null !== $state->resource && null !== $state->resourceType) {
            $arrData['activeResourceId'] = $state->resource->id;
            $arrData['activeResource'] = $state->resource->row();
        }

        // Table of the week calendar (rows with one slot per displayed weekday) and the time slots of the first column
        $arrData['rows'] = [];
        $arrData['timeSlots'] = [];
        $resource = $state->resource;
        $moduleModel = $state->module;

        if (null !== $resource && null !== $moduleModel) {
            // All seven days of the week, starting with the first day of the week of the timetable
            $daysOfWeek = $this->getDaysOfWeek($state);

            $arrData['rows'] = $this->weekTableBuilder->buildRows($daysOfWeek, $state->activeWeekTstamp, $moduleModel, $resource, $this->user->getLoggedInUser());
            $arrData['timeSlots'] = $this->weekTableBuilder->buildTimeSlots($resource, $daysOfWeek);
        }

        $arrData['isReady'] = true;

        return $arrData;
    }

    private function getResourceTypeSelectOptions(ModuleModel|null $objModule = null): array
    {
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        $rows = [];
        $arrIds = $stringUtilAdapter->deserialize($objModule->resourceBooking_resourceTypes, true);

        if (null !== ($objResourceTypes = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrIds))) {
            while ($objResourceTypes->next()) {
                $rows[] = $objResourceTypes->row();
            }
        }

        return $rows;
    }

    private function getResourceSelectOptions(ResourceBookingResourceTypeModel|null $resType = null): array
    {
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        $rows = [];

        if (null !== $resType && null !== ($objResources = $resourceBookingResourceModelAdapter->findPublishedByPid((int) $resType->id))) {
            while ($objResources->next()) {
                $rows[] = $objResources->row();
            }
        }

        return $rows;
    }

    /**
     * @throws \Exception
     */
    private function getJumpWeekDate(int $intJumpWeek, AppState $state): array
    {
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $arrReturn = [
            'disabled' => false,
        ];

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = $dateHelperAdapter->addDaysToTime($intJumpDays, $state->activeWeekTstamp);

        // The permitted weeks depend on the active resource and are stored in the session.
        $isPermittedWeek = $jumpTime >= $state->firstPermittedWeekTstamp && $jumpTime <= $state->lastPermittedWeekTstamp;

        if (!$isPermittedWeek) {
            $jumpTime = $state->activeWeekTstamp;
            $arrReturn['disabled'] = true;
        }

        if (!($state->activeWeekTstamp > 0) || null === $state->resourceType || null === $state->resource) {
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = (int) $jumpTime;

        return $arrReturn;
    }

    /**
     * @throws \Exception
     */
    private function getWeekSelection(AppState $state, int $startTstamp, int $endTstamp, bool $injectEmptyLine = false): array
    {
        $systemAdapter = $this->framework->getAdapter(System::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());

        $arrWeeks = [];

        $currentTstamp = $startTstamp;

        while ($currentTstamp <= $endTstamp) {
            $cssClass = 'past-week';

            $arrAppConfig = $state->appConfig;

            // add empty
            if ($dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig) === $currentTstamp) {
                if ($injectEmptyLine) {
                    $arrWeeks[] = [
                        'tstamp' => '',
                        'date' => '',
                        'optionText' => '-------------',
                    ];
                }

                $cssClass = 'current-week';
            }

            if ($dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig) < $currentTstamp) {
                $cssClass = 'future-week';
            }

            $tstampBeginWeek = $currentTstamp;
            $dateBeginWeek = $dateAdapter->parse('d.m.Y', $currentTstamp);
            $tstampEndWeek = strtotime($dateBeginWeek.' + 6 days');
            $dateEndWeek = $dateAdapter->parse('d.m.Y', $tstampEndWeek);
            $calWeek = $dateAdapter->parse('W', $tstampBeginWeek);
            $yearBeginWeek = $dateAdapter->parse('Y', $tstampBeginWeek);
            $arrWeeks[] = [
                'cssClass' => $cssClass,
                'tstamp' => (int) $currentTstamp,
                'tstampBeginWeek' => (int) $tstampBeginWeek,
                'tstampEndWeek' => (int) $tstampEndWeek,
                'stringBeginWeek' => $dateBeginWeek,
                'stringEndWeek' => $dateEndWeek,
                'daySpan' => $dateBeginWeek.' - '.$dateEndWeek,
                'calWeek' => (int) $calWeek,
                'year' => $yearBeginWeek,
                'optionDateStart' => $dateBeginWeek,
                'optionDateEnd' => $dateEndWeek,
                'optionText' => $this->translator->trans('MSC.weekSelectOptionText', [$calWeek, $yearBeginWeek, $dateBeginWeek, $dateEndWeek], 'contao_default'),
            ];

            $currentTstamp = $dateHelperAdapter->addDaysToTime(7, $currentTstamp);
        }

        return $arrWeeks;
    }

    /**
     * @throws \Exception
     */
    private function getWeekdays(AppState $state, int $activeWeekTstamp, ResourceBookingResourceModel|null $resourceModel = null): array
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Send weekdays, dates and day
        $arrWeek = [];

        // First get a full week,
        $arrWeekdays = $this->getDaysOfWeek($state);
        $visibleWeekdays = $this->weekTableBuilder->getVisibleWeekdaysOfResource($resourceModel, $arrWeekdays);

        foreach ($arrWeekdays as $i => $weekday) {
            // Skip weekdays on which none of the time slots is available
            if (!\in_array($weekday, $visibleWeekdays, true)) {
                continue;
            }

            $arrWeek[] = [
                'index' => $i,
                'tstamp' => strtotime($dateAdapter->parse('Y-m-d', $activeWeekTstamp).' +'.$i.' day'),
                'name' => $weekday,
                'title' => $this->translator->trans('MSC.DAYS_LONG.'.$weekday, [], 'contao_default'),
                'titleShort' => $this->translator->trans('MSC.DAYS_SHORTENED.'.$weekday, [], 'contao_default'),
                'date' => $dateAdapter->parse('d.m.Y', strtotime($dateAdapter->parse('Y-m-d', $activeWeekTstamp).' +'.$i.' day')),
            ];
        }

        return $arrWeek;
    }

    /**
     * Returns the displayed days of the week as text, e.g.
     * "Montag, 16. bis Freitag, 20. November 2026". The month and the year
     * are only repeated if they differ. The date formats and the separator
     * can be changed in the language files (RBB.daySpan*).
     *
     * @param array<int, array{tstamp: int}> $weekdays
     */
    private function getDaySpan(array $weekdays, int $activeWeekTstamp): string
    {
        if ($activeWeekTstamp <= 0) {
            return '';
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);

        if (empty($weekdays)) {
            $start = $activeWeekTstamp;
            $end = strtotime($dateAdapter->parse('Y-m-d', $activeWeekTstamp).' +6 days');
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
     * @throws \Exception
     */
    private function getDaysOfWeek(AppState $state): array
    {
        // $arrWeekdays[0] should be the weekday defined in the application configuration
        $arrWeekdays = RbbConfig::RBB_WEEKDAYS;
        $arrWeekdays = [...$arrWeekdays, ...$arrWeekdays];
        $arrAppConfig = $state->appConfig;
        $beginWeek = $arrAppConfig['beginWeek'];
        $offset = array_search($beginWeek, $arrWeekdays, true);

        return \array_slice($arrWeekdays, $offset, 7);
    }
}
