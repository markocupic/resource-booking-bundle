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
use Contao\Database;
use Contao\Date;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Markocupic\ResourceBookingBundle\Util\Str;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Trait RefreshDataTrait.
 */
trait RefreshDataTrait
{
    protected SlotFactory $slotFactory;

    protected TranslatorInterface $translator;

    #[Required]
    public function setSlotFactory(SlotFactory $slotFactory): void
    {
        $this->slotFactory = $slotFactory;
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @throws \Exception
     */
    private function getRefreshedData(AjaxResponse $ajaxResponse): array
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
        if (null === $this->getActiveResourceTypeFromSession()) {
            $ajaxResponse->setInfoMessage($this->translator->trans('RBB.MSG.selectResourceTypePlease', [], 'contao_default'));
        }

        // Show message if a resource type is selected but no resource is selected.
        if (null !== $this->getActiveResourceTypeFromSession() && null === $this->getActiveResourceFromSession()) {
            $ajaxResponse->setInfoMessage($this->translator->trans('RBB.MSG.selectResourcePlease', [], 'contao_default'));
        }

        // Get the module id
        $arrData['moduleId'] = $this->getModuleModelFromSession()->id;

        // Filter form: get resource types dropdown
        $arrData['filterBoard']['resourceTypes'] = $this->getResourceTypeSelectOptions($this->getModuleModelFromSession());

        // Filter form: get resource dropdown
        $arrData['filterBoard']['resources'] = $this->getResourceSelectOptions($this->getActiveResourceTypeFromSession());

        // Filter form get jump week array
        $arrData['filterBoard']['jumpNextWeek'] = $this->getJumpWeekDate(1);
        $arrData['filterBoard']['jumpPrevWeek'] = $this->getJumpWeekDate(-1);

        // Filter form: get date dropdown
        $arrData['filterBoard']['weekSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('tstampFirstPermittedWeek'), (int) $this->sessionBag->get('tstampLastPermittedWeek'), true);

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
        $arrData['activeWeekTstamp'] = (int) $this->sessionBag->get('activeWeekTstamp');
        $arrData['activeWeek'] = [
            'tstampStart' => $this->sessionBag->get('activeWeekTstamp'),
            'tstampEnd' => $dateHelperAdapter->addDaysToTime(6, $this->sessionBag->get('activeWeekTstamp')),
            'dateStart' => $dateAdapter->parse($configAdapter->get('dateFormat'), $this->sessionBag->get('activeWeekTstamp')),
            'dateEnd' => $dateAdapter->parse($configAdapter->get('dateFormat'), $dateHelperAdapter->addDaysToTime(6, $this->sessionBag->get('activeWeekTstamp'))),
            'weekNumber' => $dateAdapter->parse('W', $this->sessionBag->get('activeWeekTstamp')),
            'year' => $dateAdapter->parse('Y', $this->sessionBag->get('activeWeekTstamp')),
        ];

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('activeWeekTstamp'), (int) $this->sessionBag->get('tstampLastPermittedWeek'), false);

        // Weekdays
        $arrData['weekdays'] = $this->getWeekdays($this->sessionBag->get('activeWeekTstamp'), $this->getActiveResourceFromSession());

        $arrData['activeResourceTypeId'] = 'undefined';

        if (null !== $this->getActiveResourceTypeFromSession()) {
            $arrData['activeResourceType'] = $this->getActiveResourceTypeFromSession()->row();
            $arrData['activeResourceTypeId'] = $this->getActiveResourceTypeFromSession()->id;
        }

        // Generate table data
        $arrData['activeResourceId'] = 'undefined';

        if (null !== $this->getActiveResourceFromSession() && null !== $this->getActiveResourceTypeFromSession()) {
            $arrData['activeResourceId'] = $this->getActiveResourceFromSession()->id;
            $arrData['activeResource'] = $this->getActiveResourceFromSession()->row();
        }

        $arrData['rows'] = $this->getBookingTableData(
            $this->getDaysOfWeek(), // Get all seven days of one week, the week will start with the day that has been configured (config.yml).
            $this->sessionBag->get('activeWeekTstamp'),
            $this->getModuleModelFromSession(),
            $this->getActiveResourceFromSession(),
            $this->user->getLoggedInUser(),
        );

        $arrData['timeSlots'] = $this->getTimeslotData($this->getActiveResourceFromSession());

        $arrData['isReady'] = true;

        return $arrData;
    }

    /**
     * @throws \Exception
     */
    private function getActiveResourceTypeFromSession(): ResourceBookingResourceTypeModel|null
    {
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        return $resourceBookingResourceTypeModelAdapter->findById($this->sessionBag->get('resType'));
    }

    /**
     * @throws \Exception
     */
    private function getActiveResourceFromSession(): ResourceBookingResourceModel|null
    {
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        return $resourceBookingResourceModelAdapter->findById($this->sessionBag->get('res'));
    }

    /**
     * @throws \Exception
     */
    private function getModuleModelFromSession(): ModuleModel|null
    {
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        return $moduleModelAdapter->findById($this->sessionBag->get('moduleModelId'));
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
    private function getJumpWeekDate(int $intJumpWeek): array
    {
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $arrReturn = [
            'disabled' => false,
        ];

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = $dateHelperAdapter->addDaysToTime($intJumpDays, $this->sessionBag->get('activeWeekTstamp'));

        // The permitted weeks depend on the active resource and are stored in the session.
        $isPermittedWeek = $jumpTime >= (int) $this->sessionBag->get('tstampFirstPermittedWeek') && $jumpTime <= (int) $this->sessionBag->get('tstampLastPermittedWeek');

        if (!$isPermittedWeek) {
            $jumpTime = $this->sessionBag->get('activeWeekTstamp');
            $arrReturn['disabled'] = true;
        }

        if (!($this->sessionBag->get('activeWeekTstamp') > 0) || null === $this->getActiveResourceTypeFromSession() || null === $this->getActiveResourceFromSession()) {
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = (int) $jumpTime;

        return $arrReturn;
    }

    /**
     * @throws \Exception
     */
    private function getWeekSelection(int $startTstamp, int $endTstamp, bool $injectEmptyLine = false): array
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

            $arrAppConfig = $this->utils->getAppConfig();

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
    private function getWeekdays(int $activeWeekTstamp, ResourceBookingResourceModel|null $resourceModel = null): array
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Send weekdays, dates and day
        $arrWeek = [];

        // First get a full week,
        $arrWeekdays = $this->getDaysOfWeek();
        $visibleWeekdays = $this->getVisibleWeekdays($resourceModel);

        foreach ($arrWeekdays as $i => $weekday) {
            // Skip weekdays on which the resource cannot be booked
            // or on which none of the time slots is available
            if (!\in_array($weekday, $visibleWeekdays, true)) {
                continue;
            }

            $arrWeek[] = [
                'index' => $i,
                'name' => $weekday,
                'title' => $this->translator->trans('MSC.DAYS_LONG.'.$weekday, [], 'contao_default'),
                'titleShort' => $this->translator->trans('MSC.DAYS_SHORTENED.'.$weekday, [], 'contao_default'),
                'date' => $dateAdapter->parse('d.m.Y', strtotime($dateAdapter->parse('Y-m-d', $activeWeekTstamp).' +'.$i.' day')),
            ];
        }

        return $arrWeek;
    }

    /**
     * @throws \Exception
     */
    private function getDaysOfWeek(): array
    {
        // $arrWeekdays[0] should be the weekday defined in the application configuration
        $arrWeekdays = RbbConfig::RBB_WEEKDAYS;
        $arrWeekdays = [...$arrWeekdays, ...$arrWeekdays];
        $arrAppConfig = $this->utils->getAppConfig();
        $beginWeek = $arrAppConfig['beginWeek'];
        $offset = array_search($beginWeek, $arrWeekdays, true);

        return \array_slice($arrWeekdays, $offset, 7);
    }

    /**
     * Returns the weekdays that are displayed in the week calendar: the resource
     * can be booked on that weekday (tl_resource_booking_resource.allowedWeekdays)
     * and at least one of its time slots is available on that weekday
     * (tl_resource_booking_time_slot.weekdays).
     *
     * @return array<int, string>
     */
    private function getVisibleWeekdays(ResourceBookingResourceModel|null $resourceModel): array
    {
        $timeSlots = $this->getPublishedTimeSlots($resourceModel);
        $visibleWeekdays = [];

        foreach ($this->getDaysOfWeek() as $weekday) {
            if (!ResourceBookingRestriction::isWeekdayAllowed($resourceModel, $weekday)) {
                continue;
            }

            // Without any time slot the table is empty anyway: keep the weekday.
            if (empty($timeSlots)) {
                $visibleWeekdays[] = $weekday;
                continue;
            }

            foreach ($timeSlots as $timeSlot) {
                if (ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($timeSlot, $weekday)) {
                    $visibleWeekdays[] = $weekday;
                    break;
                }
            }
        }

        return $visibleWeekdays;
    }

    /**
     * Returns false if the time slot is not available on any of the displayed weekdays.
     * Such a time slot is not displayed as a row in the week calendar.
     *
     * @param array<string, mixed>   $timeSlot
     * @param array<int, string> $visibleWeekdays
     */
    private function isTimeSlotDisplayed(array $timeSlot, array $visibleWeekdays): bool
    {
        foreach ($visibleWeekdays as $weekday) {
            if (ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($timeSlot, $weekday)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPublishedTimeSlots(ResourceBookingResourceModel|null $resourceModel): array
    {
        if (null === $resourceModel) {
            return [];
        }

        $objTimeslot = $this->framework
            ->getAdapter(ResourceBookingTimeSlotModel::class)
            ->findPublishedByPid((int) $resourceModel->timeSlotType)
        ;

        $timeSlots = [];

        if (null !== $objTimeslot) {
            while ($objTimeslot->next()) {
                $timeSlots[] = $objTimeslot->current()->row();
            }
        }

        return $timeSlots;
    }

    /**
     * @throws \Exception
     */
    private function getBookingTableData(array $arrWeekdays, int $activeWeekTstamp, ModuleModel|null $moduleModel = null, ResourceBookingResourceModel|null $resourceModel = null, FrontendUser|null $user = null): array
    {
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $rows = [];

        if (null === $resourceModel) {
            return $rows;
        }

        // Resolve the list of available booking fields once; it is constant for the whole request.
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $arrAvailable = $databaseAdapter->getInstance()->listFields('tl_resource_booking');

        $objTimeslot = $resourceBookingTimeSlotModelAdapter->findPublishedByPid((int) $resourceModel->timeSlotType);
        $visibleWeekdays = $this->getVisibleWeekdays($resourceModel);
        $rowCount = 0;

        if (null !== $objTimeslot) {
            while ($objTimeslot->next()) {
                // Skip time slots that are not available on any of the displayed weekdays
                if (!$this->isTimeSlotDisplayed($objTimeslot->current()->row(), $visibleWeekdays)) {
                    continue;
                }

                $cells = [];
                $objRow = new \stdClass();

                $cssRowId = \sprintf('timeSlotModId_%s_%s', $moduleModel->id, $objTimeslot->id);
                $cssRowClass = 'rbb-time-slot-'.$objTimeslot->id;

                // Get the CSS ID
                $arrCssCellID = $stringUtilAdapter->deserialize($objTimeslot->cssID, true);

                // Override the CSS ID
                if (!empty($arrCssCellID[0])) {
                    $cssRowId = $arrCssCellID[0];
                }

                $objRow->cssRowId = $cssRowId;
                $objRow->cssRowClass = $cssRowClass;

                foreach ($arrWeekdays as $colCount => $weekday) {
                    // Skip weekdays that are not displayed in the week calendar
                    if (!\in_array($weekday, $visibleWeekdays, true)) {
                        continue;
                    }

                    $startTime = strtotime(\sprintf('+%s day', $colCount), $activeWeekTstamp) + $objTimeslot->startTime;
                    $endTime = strtotime(\sprintf('+%s day', $colCount), $activeWeekTstamp) + $objTimeslot->endTime;

                    /** @var SlotMain $slot */
                    $slot = $this->slotFactory->get($objTimeslot->id, SlotMain::MODE, $resourceModel, $startTime, $endTime);

                    $slot->setIndex($colCount);
                    $slot->setBookingCheckboxId(\sprintf('bookingCheckbox_modId_%s_%s_%s', $moduleModel->id, $rowCount, $colCount));
                    $slot->setBookingCheckboxValue(\sprintf('%s-%s-%s-%s', $objTimeslot->id, $startTime, $endTime, $activeWeekTstamp));

                    $arrBookings = [];

                    if ($slot->hasBookings) {
                        $iterator = (new \ArrayObject($slot->bookings))->getIterator();

                        while ($iterator->valid()) {
                            $booking = $iterator->current();

                            // Presets
                            $booking['bookedByFirstname'] = '';
                            $booking['bookedByLastname'] = '';

                            // Fallback
                            $booking['bookedByFullname'] = $stringUtilAdapter->decodeEntities($this->translator->trans('RBB.anonymous', [], 'contao_default'));

                            $arrAllowed = $stringUtilAdapter->deserialize($moduleModel->resourceBooking_clientPersonalData, true);

                            $objMember = $memberModelAdapter->findById($booking['member']);

                            // Send data about the booking owner (tl_member)
                            if (null !== $objMember) {
                                // However, only show the fields that have been permitted in the module settings (tl_module.resourceBooking_displayClientPersonalData).
                                if ($moduleModel->resourceBooking_displayClientPersonalData) {
                                    foreach ($arrAllowed as $fieldName) {
                                        $booking['bookedBy'.ucfirst($fieldName)] = $stringUtilAdapter->decodeEntities($objMember->$fieldName);
                                    }

                                    if (\in_array('firstname', $arrAllowed, true) && \in_array('lastname', $arrAllowed, true)) {
                                        $booking['bookedByFullname'] = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                    }
                                }

                                // Show first- and lastname of the booking owner is the currently logged in frontend user.
                                if (null !== $user && (int) $user->id === (int) $objMember->id) {
                                    $booking['bookedByFullname'] = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                }

                                // Newer send the password or session data.
                                $booking['bookedBySession'] = null;
                                $booking['bookedByPassword'] = null;
                            }

                            // Send booking details (tl_resource_booking)
                            $arrShowAlways = ['id', 'pid', 'itemsBooked', 'bookingTime', 'confirmed'];

                            // However, only show the fields that have been permitted in the module settings (tl_module.resourceBooking_bookingDetailFields).
                            if ($moduleModel->resourceBooking_displayBookingDetails) {
                                $arrAllowed = $stringUtilAdapter->deserialize($moduleModel->resourceBooking_bookingDetailFields, true);

                                foreach ($arrAvailable as $arrField) {
                                    $field = $arrField['name'];

                                    if (\in_array($field, $arrShowAlways, true) || \in_array($field, $arrAllowed, true)) {
                                        $booking['booking'.ucfirst((string) $field)] = $stringUtilAdapter->decodeEntities((string) $booking[$field]);
                                    } else {
                                        $booking['booking'.ucfirst((string) $field)] = null;
                                        $booking[$field] = null;
                                    }
                                }
                            } else {
                                foreach ($arrAvailable as $arrField) {
                                    $field = $arrField['name'];

                                    if (\in_array($field, $arrShowAlways, true)) {
                                        $booking['booking'.ucfirst((string) $field)] = $stringUtilAdapter->decodeEntities((string) $booking[$field]);
                                    } else {
                                        $booking['booking'.ucfirst((string) $field)] = null;
                                        $booking[$field] = null;
                                    }
                                }
                            }

                            // A user may only cancel their own bookings: both must exist and share the same id.
                            $booking['canCancel'] = $slot->isCancelable() && null !== $user && null !== $objMember && (int) $user->id === (int) $objMember->id;

                            $arrBookings[] = $booking;

                            $iterator->next();
                        }

                        $slot->setBookings($arrBookings);
                    }

                    $cells[] = $slot->row();
                }

                $rows[] = ['cellData' => $cells, 'rowData' => $objRow];
                ++$rowCount;
            }
        }

        return $rows;
    }

    private function getTimeslotData(ResourceBookingResourceModel|null $resourceBookingResourceModel = null): array
    {
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $utcTimeHelperAdapter = $this->framework->getAdapter(UtcTimeHelper::class);

        $timeSlots = [];

        if (null !== $resourceBookingResourceModel) {
            $objTimeslot = $resourceBookingTimeSlotModelAdapter->findPublishedByPid((int) $resourceBookingResourceModel->timeSlotType);
            $visibleWeekdays = $this->getVisibleWeekdays($resourceBookingResourceModel);

            if (null !== $objTimeslot) {
                while ($objTimeslot->next()) {
                    // Skip time slots that are not available on any of the displayed weekdays
                    // (the same rows are skipped in getBookingTableData())
                    if (!$this->isTimeSlotDisplayed($objTimeslot->current()->row(), $visibleWeekdays)) {
                        continue;
                    }

                    // Get the CSS ID
                    $arrCssCellID = $stringUtilAdapter->deserialize($objTimeslot->cssID, true);

                    // Override the CSS ID
                    $cssCellClass = null;

                    if (!empty($arrCssCellID[1])) {
                        $cssCellClass = $arrCssCellID[1];
                    }

                    $startTimeFormatted = $utcTimeHelperAdapter->parseStartTime($objTimeslot->startTime);
                    $endTimeFormatted = $utcTimeHelperAdapter->parseEndTime($objTimeslot->endTime);
                    $endTimeFormatted = '00:00' === $endTimeFormatted ? '24:00' : $endTimeFormatted;

                    $startTime = (int) $objTimeslot->startTime;
                    $endTime = (int) $objTimeslot->endTime;
                    $objTs = new \stdClass();
                    $objTs->cssClass = $cssCellClass;
                    $objTs->startTimeString = $startTimeFormatted;
                    $objTs->startTime = $startTime;
                    $objTs->endTimeString = $endTimeFormatted;
                    $objTs->timeSpanString = $startTimeFormatted.' - '.$endTimeFormatted;
                    $objTs->endTime = $endTime;
                    $timeSlots[] = $objTs;
                }
            }
        }

        return $timeSlots;
    }
}
