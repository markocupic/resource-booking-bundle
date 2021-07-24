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
use Contao\Date;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
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
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var UtcTimeHelper $stringUtilAdapter */
        $utcTimeHelperAdapter = $this->framework->getAdapter(UtcTimeHelper::class);

        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var ResourceBookingTimeSlotModel $resourceBookingTimeSlotModelAdapter */
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

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
        $rows = [];
        $arrResTypesIds = $stringUtilAdapter->deserialize($this->getModuleModelFromSession()->resourceBooking_resourceTypes, true);

        if (null !== ($objResourceTypes = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrResTypesIds))) {
            while ($objResourceTypes->next()) {
                $rows[] = $objResourceTypes->row();
            }
            $arrData['filterBoard']['resourceTypes'] = $rows;
        }
        unset($rows);

        // Filter form: get resource dropdown
        $rows = [];

        if (null !== ($objResources = $resourceBookingResourceModelAdapter->findPublishedByPid($this->getActiveResourceTypeFromSession()->id))) {
            while ($objResources->next()) {
                $rows[] = $objResources->row();
            }
            $arrData['filterBoard']['resources'] = $rows;
        }
        unset($rows);

        // Filter form get jump week array
        $arrData['filterBoard']['jumpNextWeek'] = $this->getJumpWeekDate(1);
        $arrData['filterBoard']['jumpPrevWeek'] = $this->getJumpWeekDate(-1);

        // Filter form: get date dropdown
        $arrData['filterBoard']['weekSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('tstampFirstPossibleWeek'), (int) $this->sessionBag->get('tstampLastPossibleWeek'), true);

        // Logged in user
        $arrData['userIsLoggedIn'] = false;

        if (null !== $this->user->getLoggedInUser()) {
            $arrData['userIsLoggedIn'] = true;
            $arrData['loggedInUser'] = [
                'firstname' => $this->user->getLoggedInUser()->firstname,
                'lastname' => $this->user->getLoggedInUser()->lastname,
                'gender' => $this->user->getLoggedInUser()->gender ? $this->translator->trans('MSC.'.$this->user->getLoggedInUser()->gender, [], 'contao_default') : '',
                'email' => $this->user->getLoggedInUser()->email,
                'id' => $this->user->getLoggedInUser()->id,
            ];
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
        $arrAppConfig = $this->utils->getAppConfig();

        // Send weekdays, dates and day
        $arrWeek = [];
        // First get a full week,
        // $arrWeekdays[0] should be the weekday defined in the application configuration
        $arrWeekdays = RbbConfig::RBB_WEEKDAYS;
        $arrWeekdays = [...$arrWeekdays, ...$arrWeekdays];
        $beginnWeek = $arrAppConfig['beginnWeek'];
        $offset = array_search($beginnWeek, $arrWeekdays, true);
        $arrWeekdays = \array_slice($arrWeekdays, $offset, 7);

        foreach ($arrWeekdays as $i => $weekday) {
            // Skip days
            if ($this->getModuleModelFromSession()->resourceBooking_hideDays && !\in_array($weekday, $stringUtilAdapter->deserialize($this->getModuleModelFromSession()->resourceBooking_hideDaysSelection, true), false)) {
                continue;
            }
            $arrWeek[] = [
                'index' => $i,
                'title' => $this->translator->trans('MSC.DAYS_LONG.'.$weekday, [], 'contao_default'),
                'titleShort' => $this->translator->trans('MSC.DAYS_SHORTENED.'.$weekday, [], 'contao_default'),
                'date' => $dateAdapter->parse('d.m.Y', strtotime($dateAdapter->parse('Y-m-d', $this->sessionBag->get('activeWeekTstamp')).' +'.$i.' day')),
            ];
        }

        // Weekdays
        $arrData['weekdays'] = $arrWeek;

        $arrData['activeResourceTypeId'] = 'undefined';

        if (null !== $this->getActiveResourceTypeFromSession()) {
            $arrData['activeResourceType'] = $this->getActiveResourceTypeFromSession()->row();
            $arrData['activeResourceTypeId'] = $this->getActiveResourceTypeFromSession()->id;
        }

        // Generate table data
        $arrData['activeResourceId'] = 'undefined';
        $rows = [];

        if (null !== $this->getActiveResourceFromSession() && null !== $this->getActiveResourceTypeFromSession()) {
            $arrData['activeResourceId'] = $this->getActiveResourceFromSession()->id;
            $arrData['activeResource'] = $this->getActiveResourceFromSession()->row();

            $objTimeslots = $resourceBookingTimeSlotModelAdapter->findPublishedByPid($this->getActiveResourceFromSession()->timeSlotType);
            $rowCount = 0;

            if (null !== $objTimeslots) {
                while ($objTimeslots->next()) {
                    $cells = [];
                    $objRow = new \stdClass();

                    $cssRowId = sprintf('timeSlotModId_%s_%s', $this->getModuleModelFromSession()->id, $objTimeslots->id);
                    $cssRowClass = 'time-slot-'.$objTimeslots->id;

                    // Get the CSS ID
                    $arrCssCellID = $stringUtilAdapter->deserialize($objTimeslots->cssID, true);

                    // Override the CSS ID
                    if (!empty($arrCssCellID[0])) {
                        $cssRowId = $arrCssCellID[0];
                    }

                    $objRow->cssRowId = $cssRowId;
                    $objRow->cssRowClass = $cssRowClass;

                    // Add CSS class to cell
                    $cssCellClass = null;

                    if (!empty($arrCssCellID[1])) {
                        $cssCellClass = $arrCssCellID[1];
                    }

                    foreach ($arrWeekdays as $colCount => $weekday) {
                        // Skip days
                        if ($this->getModuleModelFromSession()->resourceBooking_hideDays && !\in_array($weekday, $stringUtilAdapter->deserialize($this->getModuleModelFromSession()->resourceBooking_hideDaysSelection, true), false)) {
                            continue;
                        }

                        $startTime = strtotime(sprintf('+%s day', $colCount), $this->sessionBag->get('activeWeekTstamp')) + $objTimeslots->startTime;
                        $endTime = strtotime(sprintf('+%s day', $colCount), $this->sessionBag->get('activeWeekTstamp')) + $objTimeslots->endTime;

                        /** @var SlotMain $slot */
                        $slot = $this->slotFactory->get(SlotMain::MODE, $this->getActiveResourceFromSession(), $startTime, $endTime);
                        $slot->index = $colCount;
                        $slot->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTime, $endTime, $this->sessionBag->get('activeWeekTstamp'));
                        $slot->bookingCheckboxId = sprintf('bookingCheckbox_modId_%s_%s_%s', $this->getModuleModelFromSession()->id, $rowCount, $colCount);
                        $slot->isBookable = $slot->isBookable();

                        if ($slot->hasBookings) {
                            $objBooking = $slot->bookings;

                            while ($objBooking->next()) {
                                if (null !== $objBooking) {
                                    // Presets
                                    $objBooking->bookedByFirstname = '';
                                    $objBooking->bookedByLastname = '';
                                    $objBooking->bookedByFullname = '';

                                    $arrFields = $stringUtilAdapter->deserialize($this->getModuleModelFromSession()->resourceBooking_clientPersonalData, true);

                                    $objMember = $memberModelAdapter->findByPk($objBooking->member);

                                    if (null !== $objMember) {
                                        // Do not transmit and display sensitive data if user is not holder
                                        if ($objBooking->member !== $objMember->id && $this->getModuleModelFromSession()->resourceBooking_displayClientPersonalData && !empty($arrFields)) {
                                            foreach ($arrFields as $fieldname) {
                                                $objBooking->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($objMember->$fieldname);
                                            }

                                            if (\in_array('firstname', $arrFields, true) && \in_array('lastname', $arrFields, true)) {
                                                $objBooking->bookedByFullname = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                            }
                                        } else {
                                            foreach (array_keys($objMember->row()) as $fieldname) {
                                                $varData = $objMember->$fieldname;

                                                if ('id' === $fieldname || 'password' === $fieldname) {
                                                    continue;
                                                }

                                                // Convert bin uuids to string uuids
                                                if (!empty($varData) && !preg_match('//u', $varData)) {
                                                    if (\is_array($stringUtilAdapter->deserialize($varData))) {
                                                        $arrTemp = [];

                                                        foreach ($stringUtilAdapter->deserialize($varData) as $strUuid) {
                                                            if ($validatorAdapter->isBinaryUuid($strUuid)) {
                                                                $arrTemp[] = $stringUtilAdapter->binToUuid($strUuid);
                                                            }
                                                        }
                                                        $varData = serialize($arrTemp);
                                                    } else {
                                                        $strTemp = '';

                                                        if ($validatorAdapter->isBinaryUuid($varData)) {
                                                            $strTemp = $stringUtilAdapter->binToUuid($varData);
                                                        }
                                                        $varData = $strTemp;
                                                    }
                                                }

                                                $objBooking->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($varData);
                                                $objBooking->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($varData);
                                            }
                                            $objBooking->bookedByFullname = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                        }
                                        $objBooking->bookedBySession = null;
                                    }

                                    // Send sensitive data if it has been permitted in tl_module
                                    if ($this->getModuleModelFromSession()->resourceBooking_setBookingSubmittedFields) {
                                        $arrFields = $stringUtilAdapter->deserialize($this->getModuleModelFromSession()->resourceBooking_bookingSubmittedFields, true);

                                        foreach ($arrFields as $fieldname) {
                                            if (\in_array($fieldname, $arrFields, true)) {
                                                $objBooking->{'booking'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($objBooking->$fieldname);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $cells[] = $slot->row();
                    }
                    $rows[] = ['cellData' => $cells, 'rowData' => $objRow];
                    ++$rowCount;
                }
            }
        }

        $arrData['rows'] = $rows;
        // End generate table data

        // Get time slots
        $objTimeslots = $resourceBookingTimeSlotModelAdapter->findPublishedByPid($this->getActiveResourceFromSession()->timeSlotType);
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
        $arrData['timeSlots'] = $timeSlots;

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
