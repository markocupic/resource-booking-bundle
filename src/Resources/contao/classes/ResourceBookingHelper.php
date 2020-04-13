<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Date;
use Contao\Config;
use Contao\Controller;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingTimeSlotModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Contao\Input;

/**
 * Class ResourceBookingHelper
 * @package Markocupic\ResourceBookingBundle
 */
class ResourceBookingHelper
{

    /**
     * @param ResourceBookingWeekcalendarController $objModule
     * @return array
     */
    public static function fetchData(ResourceBookingWeekcalendarController $objModule): array
    {
        $arrData = array();

        // Handle autologout
        $arrData['opt']['autologout'] = $objModule->model->resourceBooking_autologout;
        $arrData['opt']['autologoutDelay'] = $objModule->model->resourceBooking_autologoutDelay;
        $arrData['opt']['autologoutRedirect'] = Controller::replaceInsertTags(sprintf('{{link_url::%s}}', $objModule->model->resourceBooking_autologoutRedirect));

        // Messages
        if ($objModule->objSelectedResourceType === null && !Message::hasMessages())
        {
            Message::addInfo($GLOBALS['TL_LANG']['MSG']['selectResourceTypePlease']);
        }

        if ($objModule->objSelectedResource === null && !Message::hasMessages())
        {
            Message::addInfo($GLOBALS['TL_LANG']['MSG']['selectResourcePlease'] );
        }

        // Filter form: get resource types dropdown
        $rows = array();
        $arrResTypesIds = StringUtil::deserialize($objModule->model->resourceBooking_resourceTypes, true);
        if (($objResourceTypes = ResourceBookingResourceTypeModel::findMultipleAndPublishedByIds($arrResTypesIds)) !== null)
        {
            while ($objResourceTypes->next())
            {
                $rows[] = $objResourceTypes->row();
            }
            $arrData['filterBoard']['resourceTypes'] = $rows;
        }
        unset($rows);

        // Filter form: get resource dropdown
        $rows = array();
        if (($objResources = ResourceBookingResourceModel::findPublishedByPid($objModule->objSelectedResourceType->id)) !== null)
        {
            while ($objResources->next())
            {
                $rows[] = $objResources->row();
            }
            $arrData['filterBoard']['resources'] = $rows;
        }
        unset($rows);

        // Filter form get jump week array
        $arrData['filterBoard']['jumpNextWeek'] = static::getJumpWeekDate(1, $objModule);
        $arrData['filterBoard']['jumpPrevWeek'] = static::getJumpWeekDate(-1, $objModule);

        // Filter form: get date dropdown
        $arrData['filterBoard']['weekSelection'] = ResourceBookingHelper::getWeekSelection($objModule->tstampFirstPossibleWeek, $objModule->tstampLastPossibleWeek, true);

        $objUser = $objModule->objUser;

        // Logged in user
        $arrData['loggedInUser'] = array(
            'firstname' => $objUser->firstname,
            'lastname'  => $objUser->lastname,
            'gender'    => $GLOBALS['TL_LANG'][$objUser->gender] != '' ? $GLOBALS['TL_LANG'][$objUser->gender] : $objUser->gender,
            'email'     => $objUser->email,
            'id'        => $objUser->id,
        );

        // Selected week
        $arrData['activeWeekTstamp'] = (int)$objModule->activeWeekTstamp;
        $arrData['activeWeek'] = array(
            'tstampStart' => $objModule->activeWeekTstamp,
            'tstampEnd'   => DateHelper::addDaysToTime(6, $objModule->activeWeekTstamp),
            'dateStart'   => Date::parse(Config::get('dateFormat'), $objModule->activeWeekTstamp),
            'dateEnd'     => Date::parse(Config::get('dateFormat'), DateHelper::addDaysToTime(6, $objModule->activeWeekTstamp)),
            'weekNumber'  => Date::parse('W', $objModule->activeWeekTstamp),
            'year'        => Date::parse('Y', $objModule->activeWeekTstamp),
        );

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = ResourceBookingHelper::getWeekSelection($objModule->activeWeekTstamp, DateHelper::addDaysToTime(7 * $objModule->intAheadWeeks), false);

        // Send weekdays, dates and day
        $arrWeek = array();
        $arrWeekdays = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        for ($i = 0; $i < 7; $i++)
        {
            // Skip days
            if ($objModule->model->resourceBooking_hideDays && !in_array($i, StringUtil::deserialize($objModule->model->resourceBooking_hideDaysSelection, true)))
            {
                continue;
            }
            $arrWeek[] = array(
                'index'      => $i,
                'title'      => $GLOBALS['TL_LANG']['DAYS_LONG'][$i] != '' ? $GLOBALS['TL_LANG']['DAYS_LONG'][$i] : $arrWeekdays[$i],
                'titleShort' => $GLOBALS['TL_LANG']['DAYS_SHORTED'][$i] != '' ? $GLOBALS['TL_LANG']['DAYS_SHORTED'][$i] : $arrWeekdays[$i],
                'date'       => Date::parse('d.m.Y', strtotime(Date::parse('Y-m-d', $objModule->activeWeekTstamp) . " +" . $i . " day"))
            );
        }
        // Weekdays
        $arrData['weekdays'] = $arrWeek;

        $arrData['activeResourceTypeId'] = 'undefined';
        if ($objModule->objSelectedResourceType !== null)
        {
            $arrData['activeResourceType'] = $objModule->objSelectedResourceType->row();
            $arrData['activeResourceTypeId'] = $objModule->objSelectedResourceType->id;
        }

        // Get rows
        $arrData['activeResourceId'] = 'undefined';
        if ($objModule->objSelectedResource !== null && $objModule->objSelectedResourceType !== null)
        {
            $arrData['activeResourceId'] = $objModule->objSelectedResource->id;
            $arrData['activeResource'] = $objModule->objSelectedResource->row();

            $objSelectedResource = $objModule->objSelectedResource;
            $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
            $rows = array();
            $rowCount = 0;
            if ($objTimeslots !== null)
            {
                while ($objTimeslots->next())
                {
                    $cells = array();
                    $objRow = new \stdClass();

                    $cssID = sprintf('timeSlotModId_%s_%s', $objModule->model->id, $objTimeslots->id);
                    $cssClass = 'time-slot-' . $objTimeslots->id;

                    // Get the CSS ID
                    $arrCssID = StringUtil::deserialize($objTimeslots->cssID, true);

                    // Override the CSS ID
                    if (!empty($arrCssID[0]))
                    {
                        $cssID = $arrCssID[0];
                    }

                    // Merge the CSS classes
                    if (!empty($arrCssID[1]))
                    {
                        $cssClass = trim($cssClass . ' ' . $arrCssID[1]);
                    }

                    $objRow->cssRowId = $cssID;
                    $objRow->cssRowClass = $cssClass;

                    for ($colCount = 0; $colCount < 7; $colCount++)
                    {
                        // Skip days
                        if ($objModule->model->resourceBooking_hideDays && !in_array($colCount, StringUtil::deserialize($objModule->model->resourceBooking_hideDaysSelection, true)))
                        {
                            continue;
                        }

                        $startTimestamp = strtotime(sprintf('+%s day', $colCount), $objModule->activeWeekTstamp) + $objTimeslots->startTime;
                        $endTimestamp = strtotime(sprintf('+%s day', $colCount), $objModule->activeWeekTstamp) + $objTimeslots->endTime;
                        $objTs = new \stdClass();
                        $objTs->index = $colCount;
                        $objTs->weekday = $arrWeekdays[$colCount];
                        $objTs->startTimeString = Date::parse('H:i', $startTimestamp);
                        $objTs->startTimestamp = (int)$startTimestamp;
                        $objTs->endTimeString = Date::parse('H:i', $endTimestamp);
                        $objTs->endTimestamp = (int)$endTimestamp;
                        $objTs->timeSpanString = Date::parse('H:i', $startTimestamp) . ' - ' . Date::parse('H:i', $endTimestamp);
                        $objTs->mondayTimestampSelectedWeek = (int)$objModule->activeWeekTstamp;
                        $objTs->isBooked = ResourceBookingHelper::isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->resourceId = $objSelectedResource->id;
                        $objTs->isEditable = true;
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $objTs->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTimestamp, $endTimestamp, $objModule->activeWeekTstamp);
                        $objTs->bookingCheckboxId = sprintf('bookingCheckbox_modId_%s_%s_%s', $objModule->model->id, $rowCount, $colCount);
                        if ($objTs->isBooked)
                        {
                            $objTs->isEditable = false;
                            $objBooking = ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objSelectedResource, $startTimestamp, $endTimestamp);
                            if ($objBooking !== null)
                            {
                                if ($objBooking->member === $objModule->objUser->id)
                                {
                                    $objTs->isEditable = true;
                                    $objTs->isHolder = true;
                                }

                                // Presets
                                $objTs->bookedByFirstname = '';
                                $objTs->bookedByLastname = '';
                                $objTs->bookedByFullname = '';

                                $objMember = MemberModel::findByPk($objBooking->member);
                                if ($objMember !== null)
                                {
                                    $objTs->bookedByFirstname = $objMember->firstname;
                                    $objTs->bookedByLastname = $objMember->lastname;
                                    $objTs->bookedByFullname = $objMember->firstname . ' ' . $objMember->lastname;
                                }

                                $objTs->bookingDescription = $objBooking->description;
                                $objTs->bookingId = $objBooking->id;
                            }
                        }

                        // If week lies in the past, then do not allow editing
                        if ($objTs->mondayTimestampSelectedWeek < strtotime('monday this week'))
                        {
                            $objTs->isEditable = false;
                        }

                        $cells[] = $objTs;
                    }
                    $rows[] = array('cellData' => $cells, 'rowData' => $objRow);
                    $rowCount++;
                }
            }
        }
        $arrData['rows'] = $rows;

        // Get time slots
        $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
        $timeSlots = array();
        if ($objTimeslots !== null)
        {
            while ($objTimeslots->next())
            {
                $startTimestamp = (int)$objTimeslots->startTime;
                $endTimestamp = (int)$objTimeslots->endTime;
                $objTs = new \stdClass();
                $objTs->startTimeString = UtcTime::parse('H:i', $startTimestamp);
                $objTs->startTimestamp = (int)$startTimestamp;
                $objTs->endTimeString = UtcTime::parse('H:i', $endTimestamp);
                $objTs->timeSpanString = UtcTime::parse('H:i', $startTimestamp) . ' - ' . UtcTime::parse('H:i', $endTimestamp);
                $objTs->endTimestamp = (int)$endTimestamp;
                $timeSlots[] = $objTs;
            }
        }
        $arrData['timeSlots'] = $timeSlots;

        // Get messages
        $arrData['messages'] = array();
        if (Message::hasMessages())
        {
            if (Message::hasInfo())
            {
                $arrData['messages']['info'] = Message::generateUnwrapped('FE', true);
            }
            if (Message::hasError())
            {
                $arrData['messages']['error'] = Message::generateUnwrapped('FE', true);
            }
        }

        $arrData['isReady'] = true;

        return $arrData;
    }

    /**
     * @param ResourceBookingWeekcalendarController $objModule
     * @param FrontendUser $objUser
     * @param ResourceBookingResourceModel $objResource
     * @param array $arrBookingDateSelection
     * @param int $bookingRepeatStopWeekTstamp
     * @return array
     */
    public static function prepareBookingSelection(ResourceBookingWeekcalendarController $objModule, FrontendUser $objUser, ResourceBookingResourceModel $objResource, array $arrBookingDateSelection, int $bookingRepeatStopWeekTstamp): array
    {
        $arrBookings = array();

        $objUser = FrontendUser::getInstance();

        foreach ($arrBookingDateSelection as $strTimeSlot)
        {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            $arrBooking = array(
                'timeSlotId'                          => $arrTimeSlot[0],
                'startTime'                           => (int)$arrTimeSlot[1],
                'endTime'                             => (int)$arrTimeSlot[2],
                'date'                                => '',
                'datim'                               => '',
                'mondayTimestampSelectedWeek'         => (int)$arrTimeSlot[3],
                'pid'                                 => Input::post('resourceId'),
                'description'                         => Input::post('description'),
                'member'                              => $objUser->id,
                'tstamp'                              => time(),
                'resourceAlreadyBooked'               => true,
                'resourceBlocked'                     => true,
                'resourceAlreadyBookedByLoggedInUser' => false,
                'newEntry'                            => false,
                'holder'                              => ''
            );
            $arrBookings[] = $arrBooking;

            // Handle repetitions
            if ($arrTimeSlot[3] < $bookingRepeatStopWeekTstamp)
            {
                $doRepeat = true;
                while ($doRepeat === true)
                {
                    $arrRepeat = $arrBooking;
                    $arrRepeat['startTime'] = DateHelper::addDaysToTime(7, $arrRepeat['startTime']);
                    $arrRepeat['endTime'] = DateHelper::addDaysToTime(7, $arrRepeat['endTime']);
                    $arrRepeat['mondayTimestampSelectedWeek'] = DateHelper::addDaysToTime(7, $arrRepeat['mondayTimestampSelectedWeek']);
                    $arrBookings[] = $arrRepeat;
                    // Stop repeating
                    if ($arrRepeat['mondayTimestampSelectedWeek'] >= $bookingRepeatStopWeekTstamp)
                    {
                        $doRepeat = false;
                    }
                    $arrBooking = $arrRepeat;
                    unset($arrRepeat);
                }
            }
        }

        if (count($arrBookings) > 0)
        {
            // Sort array by startTime
            usort($arrBookings, function ($a, $b) {
                return $a['startTime'] <=> $b['startTime'];
            });
        }

        foreach ($arrBookings as $index => $arrData)
        {
            // Set date
            $arrBookings[$index]['date'] = Date::parse(Config::get('dateFormat'), $arrData['startTime']);

            $arrBookings[$index]['datim'] = sprintf('%s, %s: %s - %s', Date::parse('D', $arrData['startTime']), Date::parse(Config::get('dateFormat'), $arrData['startTime']), Date::parse('H:i', $arrData['startTime']), Date::parse('H:i', $arrData['endTime']));

            if (!ResourceBookingHelper::isResourceBooked($objResource, $arrData['startTime'], $arrData['endTime']))
            {
                if (($objTimeslot = ResourceBookingTimeSlotModel::findByPk($arrData['timeSlotId'])) !== null)
                {
                    $arrBookings[$index]['resourceAlreadyBooked'] = false;
                    $arrBookings[$index]['resourceBlocked'] = false;
                }
            }
            elseif (null !== ResourceBookingModel::findOneByResourceIdStarttimeEndtimeAndMember($objResource, $arrData['startTime'], $arrData['endTime'], $arrData['member']))
            {
                $arrBookings[$index]['resourceAlreadyBooked'] = true;
                $arrBookings[$index]['resourceAlreadyBookedByLoggedInUser'] = true;
                $arrBookings[$index]['resourceBlocked'] = false;
            }
            else
            {
                $arrBookings[$index]['holder'] = '';

                $objRes = ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objResource, $arrData['startTime'], $arrData['endTime']);
                if ($objRes !== null)
                {
                    $arrBookings[$index]['holder'] = 'undefined';
                    $objMember = MemberModel::findByPk($objRes->member);
                    if ($objMember !== null)
                    {
                        $arrBookings[$index]['holder'] = StringUtil::substr($objMember->firstname, 1, '') . '. ' . $objMember->lastname;
                    }
                }
            }
        }

        return $arrBookings;
    }

    /**
     * @param ResourceBookingResourceModel $objResource
     * @param int $slotStartTime
     * @param int $slotEndTime
     * @return bool
     */
    public function isResourceBooked(ResourceBookingResourceModel $objResource, int $slotStartTime, int $slotEndTime): bool
    {
        if (ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objResource, $slotStartTime, $slotEndTime) === null)
        {
            return false;
        }
        return true;
    }

    /**
     * @param int $startTstamp
     * @param int $endTstamp
     * @param bool $injectEmptyLine
     * @return array
     */
    public static function getWeekSelection(int $startTstamp, int $endTstamp, bool $injectEmptyLine = false): array
    {
        $arrWeeks = array();

        $currentTstamp = $startTstamp;
        while ($currentTstamp <= $endTstamp)
        {
            // add empty
            if ($injectEmptyLine && DateHelper::getMondayOfCurrentWeek() == $currentTstamp)
            {
                $arrWeeks[] = array(
                    'tstamp'     => '',
                    'date'       => '',
                    'optionText' => '-------------'
                );
            }
            $tstampMonday = $currentTstamp;
            $dateMonday = Date::parse('d.m.Y', $currentTstamp);
            $tstampSunday = strtotime($dateMonday . ' + 6 days');
            $dateSunday = Date::parse('d.m.Y', $tstampSunday);
            $calWeek = Date::parse('W', $tstampMonday);
            $yearMonday = Date::parse('Y', $tstampMonday);
            $arrWeeks[] = array(
                'tstamp'       => (int)$currentTstamp,
                'tstampMonday' => (int)$tstampMonday,
                'tstampSunday' => (int)$tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan'      => $dateMonday . ' - ' . $dateSunday,
                'calWeek'      => (int)$calWeek,
                'year'         => $yearMonday,
                'optionText'   => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday)
            );

            $currentTstamp = DateHelper::addDaysToTime(7, $currentTstamp);
        }

        return $arrWeeks;
    }

    /**
     * @param int $intJumpWeek
     * @param ResourceBookingWeekcalendarController $objModule
     * @return array
     */
    public static function getJumpWeekDate(int $intJumpWeek, ResourceBookingWeekcalendarController $objModule): array
    {
        $arrReturn = array(
            'disabled' => false,
            'tstamp'   => null
        );

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = DateHelper::addDaysToTime($intJumpDays, $objModule->activeWeekTstamp);
        if (!DateHelper::isValidDate($jumpTime))
        {
            $jumpTime = $objModule->activeWeekTstamp;
            $arrReturn['disabled'] = true;
        }

        if (!$objModule->activeWeekTstamp > 0 || $objModule->objSelectedResourceType === null || $objModule->objSelectedResource === null)
        {
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = (int)$jumpTime;

        return $arrReturn;
    }

}
