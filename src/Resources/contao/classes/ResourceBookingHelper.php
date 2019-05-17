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
use Contao\StringUtil;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingTimeSlotModel;
use Contao\ResourceBookingResourceTypeModel;

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
    public static function getDataAll($objModule)
    {
        $arrData = array();

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

        // Filter form: get date dropdown
        $arrData['filterBoard']['weekSelection'] = ResourceBookingHelper::getWeekSelection(DateHelper::addDaysToTime($objModule->intBackWeeks * 7, DateHelper::getMondayOfCurrentWeek()), DateHelper::addDaysToTime($objModule->intAheadWeeks * 7, DateHelper::getMondayOfCurrentWeek()), true);

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
        $arrData['intSelectedDate'] = $objModule->intSelectedDate;
        $arrData['activeWeek'] = array(
            'tstampStart' => $objModule->intSelectedDate,
            'tstampEnd'   => DateHelper::addDaysToTime(6, $objModule->intSelectedDate),
            'dateStart'   => Date::parse(Config::get('dateFormat'), $objModule->intSelectedDate),
            'dateEnd'     => Date::parse(Config::get('dateFormat'), DateHelper::addDaysToTime(6, $objModule->intSelectedDate)),
            'weekNumber'  => Date::parse('W', $objModule->intSelectedDate),
            'year'        => Date::parse('Y', $objModule->intSelectedDate),
        );

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = ResourceBookingHelper::getWeekSelection($objModule->intSelectedDate, DateHelper::addDaysToTime(7 * $objModule->intAheadWeeks), false);

        // Send weekdays, dates and day
        $arrWeek = array();
        $arrWeekdays = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        for ($i = 0; $i < 7; $i++)
        {
            $arrWeek[] = array(
                'title'      => $GLOBALS['TL_LANG']['MSC'][$arrWeekdays[$i]][1] != '' ? $GLOBALS['TL_LANG']['MSC'][$arrWeekdays[$i]][1] : $arrWeekdays[$i],
                'titleShort' => $GLOBALS['TL_LANG']['MSC'][$arrWeekdays[$i]][0] != '' ? $GLOBALS['TL_LANG']['MSC'][$arrWeekdays[$i]][0] : $arrWeekdays[$i],
                'date'       => Date::parse('d.m.Y', strtotime(Date::parse('Y-m-d', $objModule->intSelectedDate) . " +" . $i . " day"))
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
                        $startTimestamp = strtotime(sprintf('+%s day', $colCount), $objModule->intSelectedDate) + $objTimeslots->startTime;
                        $endTimestamp = strtotime(sprintf('+%s day', $colCount), $objModule->intSelectedDate) + $objTimeslots->endTime;

                        $objTs = new \stdClass();
                        $objTs->weekday = $arrWeekdays[$colCount];
                        $objTs->startTimeString = Date::parse('H:i', $startTimestamp);
                        $objTs->startTimestamp = $startTimestamp;
                        $objTs->endTimeString = Date::parse('H:i', $endTimestamp);
                        $objTs->endTimestamp = $endTimestamp;
                        $objTs->timeSpanString = Date::parse('H:i', $startTimestamp) . ' - ' . Date::parse('H:i', $endTimestamp);
                        $objTs->mondayTimestampSelectedWeek = $objModule->intSelectedDate;
                        $objTs->isBooked = ResourceBookingHelper::isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->resourceId = $objSelectedResource->id;
                        $objTs->isEditable = true;
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $objTs->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTimestamp, $endTimestamp, $objModule->intSelectedDate);
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
                $objTs->timeSpanString = UtcDate::parse('H:i', $startTimestamp) . ' - ' . UtcDate::parse('H:i', $startTimestamp);
                $objTs->endTimestamp = $endTimestamp;
                $timeSlots[] = $objTs;
            }
        }
        $arrData['timeSlots'] = $timeSlots;

        return $arrData;
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

}
