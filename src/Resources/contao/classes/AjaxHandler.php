<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Config;
use Contao\Date;
use Contao\FrontendUser;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingTimeSlotModel;
use Contao\Input;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class AjaxHandler
 * @package Markocupic\ResourceBookingBundle
 */
class AjaxHandler
{
    /**
     * @param $objModule
     * @return JsonResponse
     */
    public function getDataAll($objModule)
    {
        $arrJson = array();
        $arrData = array();
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
        $kwSelectedDate = (int)Date::parse('W', $objModule->intSelectedDate);
        $kwNow = (int)Date::parse('W');
        $arrData['bookingRepeatsSelection'] = ResourceBookingHelper::getWeekSelection($kwSelectedDate - $kwNow - 1, $objModule->intAheadWeeks, false);

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

        // Get rows
        if ($objModule->objSelectedResource !== null && $objModule->objSelectedResourceType !== null)
        {
            $arrData['activeResource']['title'] = $objModule->objSelectedResource->title;
            $arrData['activeResourceType']['title'] = $objModule->objSelectedResourceType->title;

            $objSelectedResource = $objModule->objSelectedResource;
            $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
            $rows = array();
            $rowCount = 0;
            if ($objTimeslots !== null)
            {
                while ($objTimeslots->next())
                {
                    $cells = array();
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
                            $objRes = ResourceBookingHelper::getBookedResourcesInSlot($objSelectedResource, $startTimestamp, $endTimestamp);
                            if ($objRes !== null)
                            {
                                $objBooking = $objRes->first();
                                if ($objBooking->member === $objModule->objUser->id)
                                {
                                    $objTs->isEditable = true;
                                }

                                $objTs->bookedByFirstname = $objBooking->firstname;
                                $objTs->bookedByLastname = $objBooking->lastname;
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
                    $rows[] = $cells;
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
                $objTs->endTimestamp = $endTimestamp;
                $timeSlots[] = $objTs;
            }
        }
        $arrData['timeSlots'] = $timeSlots;

        $arrJson['data'] = $arrData;
        $arrJson['status'] = 'success';

        $response = new JsonResponse($arrJson);
        return $response->send();
    }

    /**
     * @return JsonResponse
     */
    public function sendBookingRequest($objModule)
    {
        $arrJson = array();
        $arrJson['status'] = 'error';
        $doNewInserts = true;
        $errors = 0;
        $arrBookings = array();
        $intResourceId = Input::post('resourceId');
        $objResource = ResourceBookingResourceModel::findPublishedByPk($intResourceId);
        $arrBookingDateSelection = Input::post('bookingDateSelection');
        $bookingRepeatStopWeekTstamp = Input::post('bookingRepeatStopWeekTstamp');

        if (!FE_USER_LOGGED_IN || $objResource === null || !$bookingRepeatStopWeekTstamp > 0 || !is_array($arrBookingDateSelection))
        {
            $errors++;
            $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['generalBookingError'];
        }

        if (empty($arrBookingDateSelection))
        {
            $errors++;
            $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['selectBookingDatesPlease'];
        }

        if ($errors === 0)
        {
            $objUser = FrontendUser::getInstance();

            // Prepare $arrBookings with the helper method
            $arrBookings = $this->prepareBookingSelection($objModule, $objUser, $objResource, $arrBookingDateSelection, $bookingRepeatStopWeekTstamp);

            foreach ($arrBookings as $arrBooking)
            {
                if ($arrBooking['resourceAlreadyBooked'] === true)
                {
                    $errors++;
                }
            }

            if ($errors > 0)
            {
                $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['resourceAlreadyBooked'];
            }
            else
            {
                foreach ($arrBookings as $i => $arrBooking)
                {
                    if ($arrBooking['resourceAlreadyBookedInThePastBySameMember'] === false)
                    {
                        // Set title
                        $arrBooking['title'] = sprintf('%s : %s %s %s [%s - %s]', $objResource->title, $GLOBALS['TL_LANG']['MSC']['bookingFor'], $objUser->firstname, $objUser->lastname, Date::parse(Config::get('datimFormat'), $arrBooking['startTime']), Date::parse(Config::get('datimFormat'), $arrBooking['endTime']));

                        $objBooking = new ResourceBookingModel();
                        foreach ($arrBooking as $k => $v)
                        {
                            $objBooking->{$k} = $v;
                        }
                        $objBooking->save();
                        $arrBookings[$i]['newEntry'] = true;
                    }
                    $counter++;
                }
                if ($counter === 0)
                {
                    $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['noItemsBooked'];
                }
                else
                {
                    $arrJson['status'] = 'success';
                    $arrJson['alertSuccess'] = sprintf($GLOBALS['TL_LANG']['MSG']['successfullyBookedXItems'], $objResource->title, $counter);
                }
            }
        }
        // Return $arrBookings
        $arrJson['bookingSelection'] = $arrBookings;

        $response = new JsonResponse($arrJson);
        return $response->send();
    }

    /**
     * @return JsonResponse
     */
    public function sendResourceAvailabilityRequest($objModule)
    {
        $arrJson = array();
        $arrJson['status'] = 'error';
        $arrJson['resourceIsAvailable'] = false;
        $errors = 0;
        $isAvailable = true;
        $arrBookings = array();
        $intResourceId = Input::post('resourceId');
        $objResource = ResourceBookingResourceModel::findPublishedByPk($intResourceId);
        $arrBookingDateSelection = Input::post('bookingDateSelection');
        $bookingRepeatStopWeekTstamp = Input::post('bookingRepeatStopWeekTstamp');

        if (!FE_USER_LOGGED_IN || $objResource === null || !$bookingRepeatStopWeekTstamp > 0 || !is_array($arrBookingDateSelection))
        {
            $errors++;
            $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['generalBookingError'];
        }

        if ($errors === 0)
        {
            $objUser = FrontendUser::getInstance();

            // Prepare $arrBookings with the helper method
            $arrBookings = $this->prepareBookingSelection($objModule, $objUser, $objResource, $arrBookingDateSelection, $bookingRepeatStopWeekTstamp);

            foreach ($arrBookings as $arrBooking)
            {
                if ($arrBooking['resourceAlreadyBooked'] === true)
                {
                    $isAvailable = false;
                }
            }

            $arrJson['resourceIsAvailable'] = $isAvailable;
        }
        // Return $arrBookings
        $arrJson['bookingSelection'] = $arrBookings;
        $arrJson['status'] = 'success';
        $response = new JsonResponse($arrJson);
        return $response->send();
    }

    /**
     * @return JsonResponse
     */
    public function sendCancelBookingRequest()
    {
        $arrJson = array();
        $arrJson['status'] = 'error';
        if (FE_USER_LOGGED_IN && Input::post('bookingId') > 0)
        {
            $objUser = FrontendUser::getInstance();
            $bookingId = Input::post('bookingId');
            $objBooking = ResourceBookingModel::findByPk($bookingId);
            if ($objBooking !== null)
            {
                if ($objBooking->member === $objUser->id)
                {
                    $objBooking->delete();
                    $arrJson['status'] = 'success';
                    $arrJson['alertSuccess'] = $GLOBALS['TL_LANG']['MSG']['successfullyCanceledBooking'];
                }
                else
                {
                    $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['notAllowedToCancelBooking'];
                }
            }
            else
            {
                $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['notAllowedToCancelBooking'];
            }
        }

        $response = new JsonResponse($arrJson);
        return $response->send();
    }

    /**
     * @param $objModule
     * @param $objUser
     * @param $objResource
     * @param $arrBookingDateSelection
     * @param $bookingRepeatStopWeekTstamp
     * @return array
     */
    protected function prepareBookingSelection($objModule, $objUser, $objResource, $arrBookingDateSelection, $bookingRepeatStopWeekTstamp)
    {
        $arrBookings = array();

        $objUser = FrontendUser::getInstance();

        foreach ($arrBookingDateSelection as $strTimeSlot)
        {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            $arrBooking = array(
                'timeSlotId'                                 => $arrTimeSlot[0],
                'startTime'                                  => $arrTimeSlot[1],
                'endTime'                                    => $arrTimeSlot[2],
                'mondayTimestampSelectedWeek'                => $arrTimeSlot[3],
                'pid'                                        => Input::post('resourceId'),
                'description'                                => Input::post('description'),
                'member'                                     => $objUser->id,
                'firstname'                                  => $objUser->firstname,
                'lastname'                                   => $objUser->lastname,
                'tstamp'                                     => time(),
                'resourceAlreadyBooked'                      => true,
                'resourceAlreadyBookedInThePastBySameMember' => false,
                'newEntry'                                   => false,
            );
            $arrBookings[] = $arrBooking;

            // Handle repetitions
            if ($arrTimeSlot[3] < $bookingRepeatStopWeekTstamp)
            {
                $doRepeat = true;
                $arrRepeat = $arrBooking;
                while ($doRepeat === true)
                {
                    $arrRepeat['startTime'] = DateHelper::addDaysToTime(7, $arrRepeat['startTime']);
                    $arrRepeat['endTime'] = DateHelper::addDaysToTime(7, $arrRepeat['endTime']);
                    $arrRepeat['mondayTimestampSelectedWeek'] = DateHelper::addDaysToTime(7, $arrRepeat['mondayTimestampSelectedWeek']);
                    $arrBookings[] = $arrRepeat;
                    if ($arrRepeat['mondayTimestampSelectedWeek'] >= $bookingRepeatStopWeekTstamp)
                    {
                        $doRepeat = false;
                    }
                }
            }
        }
        foreach ($arrBookings as $index => $arrData)
        {
            if (!ResourceBookingHelper::isResourceBooked($objResource, $arrData['startTime'], $arrData['endTime']))
            {
                if (($objTimeslot = ResourceBookingTimeSlotModel::findByPk($arrData['timeSlotId'])) !== null)
                {
                    $arrBookings[$index]['resourceAlreadyBooked'] = false;
                }
            }
            elseif (null !== ResourceBookingModel::findOneByResourceIdStarttimeEndtimeAndOwnerId($objResource, $arrData['startTime'], $arrData['endTime'], $arrData['member']))
            {
                $arrBookings[$index]['resourceAlreadyBooked'] = false;
                $arrBookings[$index]['resourceAlreadyBookedInThePastBySameMember'] = true;
            }
        }

        return $arrBookings;
    }

}
