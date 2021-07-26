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
use Contao\Database;
use Contao\Date;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Str;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;

/**
 * Trait RefreshDataTrait.
 */
trait RefreshDataTrait
{
    /**
     * @throws \Exception
     */
    private function refreshData(): array
    {
        $systemAdapter = $this->framework->getAdapter(System::class);
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $strAdapter = $this->framework->getAdapter(Str::class);

        $arrData = [];

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());

        // Messages
        if (null === $this->getActiveResourceTypeFromSession() && !$messageAdapter->hasMessages()) {
            $messageAdapter->addInfo($this->translator->trans('RBB.MSG.selectResourceTypePlease', [], 'contao_default'));
        }

        if (null === $this->getActiveResourceFromSession() && !$messageAdapter->hasMessages()) {
            $messageAdapter->addInfo($this->translator->trans('RBB.MSG.selectResourcePlease', [], 'contao_default'));
        }

        // Filter form: get resource types dropdown
        $arrData['filterBoard']['resourceTypes'] = $this->getResourceTypeSelectOptions($this->getModuleModelFromSession());

        // Filter form: get resource dropdown
        $arrData['filterBoard']['resources'] = $this->getResourceSelectOptions($this->getActiveResourceTypeFromSession());

        // Filter form get jump week array
        $arrData['filterBoard']['jumpNextWeek'] = $this->getJumpWeekDate(1);
        $arrData['filterBoard']['jumpPrevWeek'] = $this->getJumpWeekDate(-1);

        // Filter form: get date dropdown
        $arrData['filterBoard']['weekSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('tstampFirstPossibleWeek'), (int) $this->sessionBag->get('tstampLastPossibleWeek'), true);

        // Logged in user
        $arrData['userHasLoggedIn'] = false;
        $arrData['loggedInUser'] = null;

        if (null !== $this->user->getLoggedInUser()) {
            $arrData['userHasLoggedIn'] = true;
            $arrData['loggedInUser'] = array_map(static fn ($v) => $strAdapter->convertBinUuidsToStringUuids($v), $this->user->getModel()->row());
            $arrData['loggedInUser']['gender'] = $this->translator->trans('MSC.'.$this->user->getModel()->gender, [], 'contao_default');
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
        $arrData['bookingRepeatsSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('activeWeekTstamp'), (int) $this->sessionBag->get('tstampLastPossibleWeek'), false);

        // Weekdays
        $arrData['weekdays'] = $this->getWeekdays($this->sessionBag->get('activeWeekTstamp'), $this->getModuleModelFromSession());

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
            $this->getDaysOfWeek(), // Get all seven days of one week, the week will start with the  the day that has been configured (config.yml).
            $this->sessionBag->get('activeWeekTstamp'),
            $this->getModuleModelFromSession(),
            $this->getActiveResourceFromSession(),
            $this->user->getLoggedInUser()
        );

        $arrData['timeSlots'] = $this->getTimeslotData($this->getActiveResourceFromSession());

        // Get messages
        $arrData['messages'] = [];

        if ($messageAdapter->hasMessages()) {
            if ($messageAdapter->hasInfo()) {
                $arrData['messages']['info'] = $messageAdapter->generateUnwrapped('FE', true);
            }

            if ($messageAdapter->hasError()) {
                $arrData['messages']['error'] = $messageAdapter->generateUnwrapped('FE', true);
            }
        }

        $arrData['isReady'] = true;

        return $arrData;
    }

    private function getResourceTypeSelectOptions(?ModuleModel $objModule = null): array
    {
        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        $rows = [];
        $arrIds = StringUtil::deserialize($objModule->resourceBooking_resourceTypes, true);

        if (null !== ($objResourceTypes = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrIds))) {
            while ($objResourceTypes->next()) {
                $rows[] = $objResourceTypes->row();
            }
        }

        return $rows;
    }

    private function getResourceSelectOptions(?ResourceBookingResourceTypeModel $ResType = null): array
    {
        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        $rows = [];

        if (null !== ($objResources = $resourceBookingResourceModelAdapter->findPublishedByPid($ResType->id))) {
            while ($objResources->next()) {
                $rows[] = $objResources->row();
            }
        }

        return $rows;
    }

    /**
     * @throws \Exception
     */
    private function getWeekSelection(int $startTstamp, int $endTstamp, bool $injectEmptyLine = false): array
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
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

            $tstampBeginnWeek = $currentTstamp;
            $dateBeginnWeek = $dateAdapter->parse('d.m.Y', $currentTstamp);
            $tstampEndWeek = strtotime($dateBeginnWeek.' + 6 days');
            $dateEndWeek = $dateAdapter->parse('d.m.Y', $tstampEndWeek);
            $calWeek = $dateAdapter->parse('W', $tstampBeginnWeek);
            $yearBeginnWeek = $dateAdapter->parse('Y', $tstampBeginnWeek);
            $arrWeeks[] = [
                'cssClass' => $cssClass,
                'tstamp' => (int) $currentTstamp,
                'tstampBeginnWeek' => (int) $tstampBeginnWeek,
                'tstampEndWeek' => (int) $tstampEndWeek,
                'stringBeginnWeek' => $dateBeginnWeek,
                'stringEndWeek' => $dateEndWeek,
                'daySpan' => $dateBeginnWeek.' - '.$dateEndWeek,
                'calWeek' => (int) $calWeek,
                'year' => $yearBeginnWeek,
                'optionDateStart' => $dateBeginnWeek,
                'optionDateEnd' => $dateEndWeek,
                'optionText' => $this->translator->trans('MSC.weekSelectOptionText', [$calWeek, $yearBeginnWeek, $dateBeginnWeek, $dateEndWeek], 'contao_default'),
            ];

            $currentTstamp = $dateHelperAdapter->addDaysToTime(7, $currentTstamp);
        }

        return $arrWeeks;
    }

    /**
     * @throws \Exception
     */
    private function getJumpWeekDate(int $intJumpWeek): array
    {
        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $arrReturn = [
            'disabled' => false,
            'tstamp' => null,
        ];

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = $dateHelperAdapter->addDaysToTime($intJumpDays, $this->sessionBag->get('activeWeekTstamp'));

        // Get app config
        $arrAppConfig = $this->utils->getAppConfig();

        if (!$dateHelperAdapter->isValidDate($jumpTime, $arrAppConfig)) {
            $jumpTime = $this->sessionBag->get('activeWeekTstamp');
            $arrReturn['disabled'] = true;
        }

        if (!$this->sessionBag->get('activeWeekTstamp') > 0 || null === $this->getActiveResourceTypeFromSession() || null === $this->getActiveResourceFromSession()) {
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = (int) $jumpTime;

        return $arrReturn;
    }

    private function getTimeslotData(?ResourceBookingResourceModel $resourceBookingResourceModel = null)
    {
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $utcTimeHelperAdapter = $this->framework->getAdapter(UtcTimeHelper::class);

        $objTimeslots = $resourceBookingTimeSlotModelAdapter->findPublishedByPid($resourceBookingResourceModel->timeSlotType);
        $timeSlots = [];

        if (null !== $objTimeslots) {
            while ($objTimeslots->next()) {
                // Get the CSS ID
                $arrCssCellID = $stringUtilAdapter->deserialize($objTimeslots->cssID, true);

                // Override the CSS ID
                $cssCellClass = null;

                if (!empty($arrCssCellID[1])) {
                    $cssCellClass = $arrCssCellID[1];
                }
                $startTime = (int) $objTimeslots->startTime;
                $endTime = (int) $objTimeslots->endTime;
                $objTs = new \stdClass();
                $objTs->cssClass = $cssCellClass;
                $objTs->startTimeString = $utcTimeHelperAdapter->parse('H:i', $startTime);
                $objTs->startTime = (int) $startTime;
                $objTs->endTimeString = $utcTimeHelperAdapter->parse('H:i', $endTime);
                $objTs->timeSpanString = $utcTimeHelperAdapter->parse('H:i', $startTime).' - '.$utcTimeHelperAdapter->parse('H:i', $endTime);
                $objTs->endTime = (int) $endTime;
                $timeSlots[] = $objTs;
            }
        }

        return $timeSlots;
    }

    private function getWeekdays(int $activeWeekTstamp, ModuleModel $moduleModel): array
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Send weekdays, dates and day
        $arrWeek = [];

        // First get a full week,
        $arrWeekdays = $this->getDaysOfWeek();

        foreach ($arrWeekdays as $i => $weekday) {
            // Skip days
            if ($moduleModel->resourceBooking_hideDays && !\in_array($weekday, $stringUtilAdapter->deserialize($moduleModel->resourceBooking_hideDaysSelection, true), false)) {
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

    private function getBookingTableData(array $arrWeekdays, int $activeWeekTstamp, ?ModuleModel $moduleModel = null, ?ResourceBookingResourceModel $resourceModel = null, ?FrontendUser $user = null): array
    {
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $strAdapter = $this->framework->getAdapter(Str::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $rows = [];

        if (null === $resourceModel) {
            return $rows;
        }

        $objTimeslots = $resourceBookingTimeSlotModelAdapter->findPublishedByPid($resourceModel->timeSlotType);
        $rowCount = 0;

        if (null !== $objTimeslots) {
            while ($objTimeslots->next()) {
                $cells = [];
                $objRow = new \stdClass();

                $cssRowId = sprintf('timeSlotModId_%s_%s', $moduleModel->id, $objTimeslots->id);
                $cssRowClass = 'time-slot-'.$objTimeslots->id;

                // Get the CSS ID
                $arrCssCellID = $stringUtilAdapter->deserialize($objTimeslots->cssID, true);

                // Override the CSS ID
                if (!empty($arrCssCellID[0])) {
                    $cssRowId = $arrCssCellID[0];
                }

                $objRow->cssRowId = $cssRowId;
                $objRow->cssRowClass = $cssRowClass;

                foreach ($arrWeekdays as $colCount => $weekday) {
                    // Skip days
                    if ($moduleModel->resourceBooking_hideDays && !\in_array($weekday, $stringUtilAdapter->deserialize($moduleModel->resourceBooking_hideDaysSelection, true), false)) {
                        continue;
                    }

                    $startTime = strtotime(sprintf('+%s day', $colCount), $activeWeekTstamp) + $objTimeslots->startTime;
                    $endTime = strtotime(sprintf('+%s day', $colCount), $activeWeekTstamp) + $objTimeslots->endTime;

                    /** @var SlotMain $slot */
                    $slot = $this->slotFactory->get(SlotMain::MODE, $resourceModel, $startTime, $endTime);
                    $slot->index = $colCount;
                    $slot->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTime, $endTime, $activeWeekTstamp);
                    $slot->bookingCheckboxId = sprintf('bookingCheckbox_modId_%s_%s_%s', $moduleModel->id, $rowCount, $colCount);

                    if ($slot->hasBookings) {
                        while ($slot->bookings->next()) {
                            $objBooking = $slot->bookings->current();

                            if (null !== $objBooking) {
                                // Presets
                                $objBooking->bookedByFirstname = '';
                                $objBooking->bookedByLastname = '';

                                // Fallback
                                $objBooking->bookedByFullname = $stringUtilAdapter->decodeEntities($this->translator->trans('RBB.anonymous', [], 'contao_default'));

                                $arrAllowedMemberFields = $stringUtilAdapter->deserialize($moduleModel->resourceBooking_clientPersonalData, true);

                                $objMember = $memberModelAdapter->findByPk($objBooking->member);

                                if (null !== $objMember) {
                                    // Do not transmit and display sensitive data if user is not holder
                                    if ($user && (int) $user->id !== (int) $objBooking->member) {
                                        if ($moduleModel->resourceBooking_displayClientPersonalData && !empty($arrAllowedMemberFields)) {
                                            foreach ($arrAllowedMemberFields as $fieldname) {
                                                $objBooking->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($objMember->$fieldname);
                                            }

                                            if (\in_array('firstname', $arrAllowedMemberFields, true) && \in_array('lastname', $arrAllowedMemberFields, true)) {
                                                $objBooking->bookedByFullname = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                            }
                                        }
                                    } else {
                                        foreach (array_keys($objMember->row()) as $fieldname) {
                                            $varData = $strAdapter->convertBinUuidsToStringUuids($objMember->$fieldname);

                                            $objBooking->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($varData);
                                            $objBooking->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($varData);
                                        }
                                        $objBooking->bookedByFullname = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                    }
                                    $objBooking->bookedBySession = null;
                                    $objBooking->bookedByPassword = null;
                                }

                                // Send sensitive data if it has been permitted in tl_module
                                $databaseAdapter = $this->framework->getAdapter(Database::class);
                                $arrAvailable = $databaseAdapter->getInstance()->listFields('tl_resource_booking');

                                if ($moduleModel->resourceBooking_setBookingSubmittedFields) {
                                    $arrAllowedBookingFields = $stringUtilAdapter->deserialize($moduleModel->resourceBooking_bookingSubmittedFields, true);

                                    foreach ($arrAvailable as $arrField) {
                                        $field = $arrField['name'];

                                        if (\in_array($field, $arrAllowedBookingFields, true)) {
                                            $objBooking->{'booking'.ucfirst((string) $field)} = $stringUtilAdapter->decodeEntities((string) $objBooking->$field);
                                        } else {
                                            $objBooking->{'booking'.ucfirst((string) $field)} = null;
                                        }
                                    }
                                } else {
                                    foreach ($arrAvailable as $arrField) {
                                        $field = $arrField['name'];
                                        $objBooking->{'booking'.ucfirst((string) $field)} = null;
                                    }
                                }

                                $objBooking->title = null;
                                $objBooking->description = null;
                                $objBooking->moduleId = null;
                                $objBooking->bookingId = null;
                                $objBooking->bookingPid = null;
                                $objBooking->canCancel = $slot->isCancelable() && (int) $user->id === (int) $objMember->id;
                            }
                        }
                    }
                    $cells[] = $slot->row();
                }
                $rows[] = ['cellData' => $cells, 'rowData' => $objRow];
                ++$rowCount;
            }
        }

        return $rows;
    }

    private function getDaysOfWeek(): array
    {
        // $arrWeekdays[0] should be the weekday defined in the application configuration
        $arrWeekdays = RbbConfig::RBB_WEEKDAYS;
        $arrWeekdays = [...$arrWeekdays, ...$arrWeekdays];
        $arrAppConfig = $this->utils->getAppConfig();
        $beginnWeek = $arrAppConfig['beginnWeek'];
        $offset = array_search($beginnWeek, $arrWeekdays, true);

        return \array_slice($arrWeekdays, $offset, 7);
    }

    /**
     * @throws \Exception
     */
    private function getActiveResourceFromSession(): ?ResourceBookingResourceModel
    {
        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        return $resourceBookingResourceModelAdapter->findByPk($this->sessionBag->get('res'));
    }

    /**
     * @throws \Exception
     */
    private function getActiveResourceTypeFromSession(): ?ResourceBookingResourceTypeModel
    {
        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        return $resourceBookingResourceTypeModelAdapter->findByPk($this->sessionBag->get('resType'));
    }

    /**
     * @throws \Exception
     */
    private function getModuleModelFromSession(): ?ModuleModel
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        return $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));
    }
}
