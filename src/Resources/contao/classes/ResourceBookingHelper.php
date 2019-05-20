<?php

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
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingTimeSlotModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\FrontendUser;

use Contao\Input;

/**
 * Class ResourceBookingHelper
 * @package Markocupic\ResourceBookingBundle
 */
class ResourceBookingHelper
{

    /**
     * @param $objModule
     * @return array
     */
    public static function fetchData($objModule)
    {
        $arrData = array();

        // Messages
        if ($objModule->objSelectedResourceType === null && !Message::hasMessages())
        {
            Message::addInfo('Bitte wählen Sie einen Resourcentyp aus.');
        }
        if ($objModule->objSelectedResource === null && !Message::hasMessages())
        {
            Message::addInfo('Bitte wählen Sie eine Resource aus.');
        }

        // Filter form: get resource types dropdown
        $rows = array();
        $arrResTypesIds = StringUtil::deserialize($objModule->resourceBooking_resourceTypes, true);
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
        $arrData['tstampActiveWeek'] = $objModule->tstampActiveWeek;
        $arrData['activeWeek'] = array(
            'tstampStart' => $objModule->tstampActiveWeek,
            'tstampEnd'   => DateHelper::addDaysToTime(6, $objModule->tstampActiveWeek),
            'dateStart'   => Date::parse(Config::get('dateFormat'), $objModule->tstampActiveWeek),
            'dateEnd'     => Date::parse(Config::get('dateFormat'), DateHelper::addDaysToTime(6, $objModule->tstampActiveWeek)),
            'weekNumber'  => Date::parse('W', $objModule->tstampActiveWeek),
            'year'        => Date::parse('Y', $objModule->tstampActiveWeek),
        );

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = ResourceBookingHelper::getWeekSelection($objModule->tstampActiveWeek, DateHelper::addDaysToTime(7 * $objModule->intAheadWeeks), false);

        // Send weekdays, dates and day
        $arrWeek = array();
        $arrWeekdays = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        for ($i = 0; $i < 7; $i++)
        {
            // Skip days
            if ($objModule->resourceBooking_hideDays && !in_array($i, StringUtil::deserialize($objModule->resourceBooking_hideDaysSelection, true)))
            {
                continue;
            }
            $arrWeek[] = array(
                'index'      => $i,
                'title'      => $GLOBALS['TL_LANG']['DAYS_LONG'][$i] != '' ? $GLOBALS['TL_LANG']['DAYS_LONG'][$i] : $arrWeekdays[$i],
                'titleShort' => $GLOBALS['TL_LANG']['DAYS_SHORT'][$i] != '' ? $GLOBALS['TL_LANG']['DAYS_SHORT'][$i] : $arrWeekdays[$i],
                'date'       => Date::parse('d.m.Y', strtotime(Date::parse('Y-m-d', $objModule->tstampActiveWeek) . " +" . $i . " day"))
            );
        }
        // Weekdays
        $arrData['weekdays'] = $arrWeek;

        if ($objModule->objSelectedResourceType !== null)
        {
            $arrData['activeResourceType'] = $objModule->objSelectedResourceType->row();
        }

        // Get rows
        if ($objModule->objSelectedResource !== null && $objModule->objSelectedResourceType !== null)
        {
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
                    $objRow->cssRowClass = "time-slot-" . $objTimeslots->id;

                    for ($colCount = 0; $colCount < 7; $colCount++)
                    {
                        // Skip days
                        if ($objModule->resourceBooking_hideDays && !in_array($colCount, StringUtil::deserialize($objModule->resourceBooking_hideDaysSelection, true)))
                        {
                            continue;
                        }

                        $startTimestamp = strtotime(sprintf('+%s day', $colCount), $objModule->tstampActiveWeek) + $objTimeslots->startTime;
                        $endTimestamp = strtotime(sprintf('+%s day', $colCount), $objModule->tstampActiveWeek) + $objTimeslots->endTime;
                        $objTs = new \stdClass();
                        $objTs->index = $colCount;
                        $objTs->weekday = $arrWeekdays[$colCount];
                        $objTs->startTimeString = Date::parse('H:i', $startTimestamp);
                        $objTs->startTimestamp = $startTimestamp;
                        $objTs->endTimeString = Date::parse('H:i', $endTimestamp);
                        $objTs->endTimestamp = $endTimestamp;
                        $objTs->timeSpanString = Date::parse('H:i', $startTimestamp) . ' - ' . Date::parse('H:i', $endTimestamp);
                        $objTs->mondayTimestampSelectedWeek = $objModule->tstampActiveWeek;
                        $objTs->isBooked = ResourceBookingHelper::isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->resourceId = $objSelectedResource->id;
                        $objTs->isEditable = true;
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $objTs->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTimestamp, $endTimestamp, $objModule->tstampActiveWeek);
                        $objTs->bookingCheckboxId = sprintf('bookingCheckbox_%s_%s', $rowCount, $colCount);
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

                                $objTs->bookedByFirstname = $objBooking->firstname;
                                $objTs->bookedByLastname = $objBooking->lastname;
                                $objTs->bookedByFullname = $objBooking->firstname . ' ' . $objBooking->lastname;
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
                $startTimestamp = $objTimeslots->startTime;
                $endTimestamp = $objTimeslots->endTime;
                $objTs = new \stdClass();
                $objTs->startTimeString = UtcDate::parse('H:i', $startTimestamp);
                $objTs->startTimestamp = $startTimestamp;
                $objTs->endTimeString = UtcDate::parse('H:i', $endTimestamp);
                $objTs->timeSpanString = UtcDate::parse('H:i', $startTimestamp) . ' - ' . UtcDate::parse('H:i', $endTimestamp);
                $objTs->endTimestamp = $endTimestamp;
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
     * @param $objModule
     * @param $objUser
     * @param $objResource
     * @param $arrBookingDateSelection
     * @param $bookingRepeatStopWeekTstamp
     * @return array
     */
    public static function prepareBookingSelection($objModule, $objUser, $objResource, $arrBookingDateSelection, $bookingRepeatStopWeekTstamp)
    {
        $arrBookings = array();

        $objUser = FrontendUser::getInstance();

        foreach ($arrBookingDateSelection as $strTimeSlot)
        {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            $arrBooking = array(
                'timeSlotId'                          => $arrTimeSlot[0],
                'startTime'                           => $arrTimeSlot[1],
                'endTime'                             => $arrTimeSlot[2],
                'date'                                => '',
                'datim'                               => '',
                'mondayTimestampSelectedWeek'         => $arrTimeSlot[3],
                'pid'                                 => Input::post('resourceId'),
                'description'                         => Input::post('description'),
                'member'                              => $objUser->id,
                'firstname'                           => $objUser->firstname,
                'lastname'                            => $objUser->lastname,
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
     * @param $objResource
     * @param $slotStartTime
     * @param $slotEndTime
     * @return bool
     */
    public function isResourceBooked($objResource, $slotStartTime, $slotEndTime)
    {
        if (ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objResource, $slotStartTime, $slotEndTime) === null)
        {
            return false;
        }
        return true;
    }

    /**
     * @param $startTstamp
     * @param $endTstamp
     * @param bool $injectEmptyLine
     * @return array
     */
    public static function getWeekSelection($startTstamp, $endTstamp, $injectEmptyLine = false)
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
                'tstamp'       => $currentTstamp,
                'tstampMonday' => $tstampMonday,
                'tstampSunday' => $tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan'      => $dateMonday . ' - ' . $dateSunday,
                'calWeek'      => $calWeek,
                'year'         => $yearMonday,
                'optionText'   => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday)
            );

            $currentTstamp = DateHelper::addDaysToTime(7, $currentTstamp);
        }

        return $arrWeeks;
    }

    /**
     * Get 1 Week back/ahead date
     * @param $intJumpWeek
     * @param $objModule
     * @return array
     */
    public static function getJumpWeekDate($intJumpWeek, $objModule)
    {
        $arrReturn = array(
            'disabled' => false,
            'tstamp'   => null
        );

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = DateHelper::addDaysToTime($intJumpDays, $objModule->tstampActiveWeek);
        if (!DateHelper::isValidDate($jumpTime))
        {
            $jumpTime = $objModule->tstampActiveWeek;
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = $jumpTime;

        return $arrReturn;
    }

}
