<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 08.05.2019
 * Time: 13:10
 */

namespace Markocupic\ResourceReservationBundle;

use Contao\Date;
use Contao\FrontendUser;
use Contao\ResourceReservationModel;
use Contao\ResourceReservationResourceModel;
use Contao\ResourceReservationResourceTypeModel;
use Contao\ResourceReservationTimeSlotModel;
use Contao\Input;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxHandler
{
    /**
     * @param $objModule
     * @return JsonResponse
     */
    public function getDataAll($objModule)
    {
        $arrItems = array();
        $arrJson = array();

        $arrJson['status'] = 'success';
        $arrJson['intSelectedDate'] = $objModule->intSelectedDate;

        // Send dates and day
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

        $arrJson['weekdays'] = $arrWeek;

        // Get rows
        if ($objModule->objSelectedResource !== null && $objModule->objSelectedResourceType !== null)
        {
            $arrJson['activeResource']['title'] = $objModule->objSelectedResource->title;
            $arrJson['activeResourceType']['title'] = $objModule->objSelectedResourceType->title;

            $objSelectedResource = $objModule->objSelectedResource;
            $objTimeslots = ResourceReservationTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
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
                        $objTs->isBooked = ResourceReservationHelper::isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);
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
                            $objRes = ResourceReservationHelper::getBookedResourcesInSlot($objSelectedResource, $startTimestamp, $endTimestamp);
                            if ($objRes !== null)
                            {
                                $objReservation = $objRes->first();
                                if ($objReservation->member === $objModule->objUser->id)
                                {
                                    $objTs->isEditable = true;
                                }

                                $objTs->bookedByFirstname = $objReservation->firstname;
                                $objTs->bookedByLastname = $objReservation->lastname;
                                $objTs->bookingDescription = $objReservation->description;
                                $objTs->bookingId = $objReservation->id;
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
        $arrJson['rows'] = $rows;

        // Get time slots
        $objTimeslots = ResourceReservationTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
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
        $arrJson['timeSlots'] = $timeSlots;

        $response = new JsonResponse($arrJson);
        return $response->send();
    }

    /**
     * @return JsonResponse
     */
    public function sendBookingRequest()
    {
        $arrJson = array();
        $arrJson['status'] = 'error';
        if (FE_USER_LOGGED_IN && Input::post('bookingRepeatStopWeekTstamp') > 0 && Input::post('resourceId') > 0 && Input::post('bookedTimeSlots'))
        {
            if (is_array(Input::post('bookedTimeSlots')) && !empty(Input::post('bookedTimeSlots')))
            {
                $error = 0;
                $counter = 0;
                $arrBookings = array();
                $arrResObj = array();

                $objUser = FrontendUser::getInstance();
                $objResource = ResourceReservationResourceModel::findByPk(Input::post('resourceId'));
                if ($objResource !== null)
                {
                    foreach (Input::post('bookedTimeSlots') as $strTimeSlot)
                    {
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $arrTimeSlot = explode('-', $strTimeSlot);
                        $arrBooking = array(
                            'timeSlotId'                  => $arrTimeSlot[0],
                            'startTime'                   => $arrTimeSlot[1],
                            'endTime'                     => $arrTimeSlot[2],
                            'mondayTimestampSelectedWeek' => $arrTimeSlot[3],
                            'pid'                         => Input::post('resourceId'),
                            'description'                 => Input::post('description'),
                            'member'                      => $objUser->id,
                            'firstname'                   => $objUser->firstname,
                            'lastname'                    => $objUser->lastname,
                            'tstamp'                      => time(),
                            'title'                       => $objResource->title . ': Booking for ' . $objUser->firstname . ' ' . $objUser->lastname
                        );
                        $arrBookings[] = $arrBooking;

                        // Handle repetitions
                        if ($arrTimeSlot[3] < Input::post('bookingRepeatStopWeekTstamp'))
                        {
                            $doRepeat = true;
                            $arrRepeat = $arrBooking;
                            while ($doRepeat === true)
                            {
                                $arrRepeat['startTime'] = strtotime(Date::parse('Y-m-d H:i:s', $arrRepeat['startTime']) . ' + 7 days');
                                $arrRepeat['endTime'] = strtotime(Date::parse('Y-m-d H:i:s', $arrRepeat['endTime']) . ' + 7 days');
                                $arrRepeat['mondayTimestampSelectedWeek'] = strtotime(Date::parse('Y-m-d H:i:s', $arrRepeat['mondayTimestampSelectedWeek']) . ' + 7 days');
                                $arrBookings[] = $arrRepeat;
                                if ($arrRepeat['mondayTimestampSelectedWeek'] >= Input::post('bookingRepeatStopWeekTstamp'))
                                {
                                    $doRepeat = false;
                                }
                            }
                        }
                    }
                    foreach ($arrBookings as $arrData)
                    {
                        if (!ResourceReservationHelper::isResourceBooked($objResource, $arrData['startTime'], $arrData['endTime']))
                        {
                            if (($objTimeslot = ResourceReservationTimeSlotModel::findByPk($arrData['timeSlotId'])) !== null)
                            {
                                $objReservation = new ResourceReservationModel();
                                foreach ($arrData as $k => $v)
                                {
                                    $objReservation->{$k} = $v;
                                }
                                $arrResObj[] = $objReservation;
                                $counter++;
                            }
                        }
                        elseif (null !== ResourceReservationModel::findOneByResourceIdStarttimeEndtimeAndOwnerId($objResource, $arrData['startTime'], $arrData['endTime'], $arrData['member']))
                        {
                            $counter++;
                        }
                        else
                        {
                            $error++;
                            $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['resourceAlreadyBooked'];
                        }
                    }
                }
                else
                {
                    $error++;
                    $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['noResourceSelected'];
                }

                if ($error === 0)
                {
                    foreach ($arrResObj as $objReservation)
                    {
                        $objReservation->save();
                    }

                    $arrJson['status'] = 'success';
                    $arrJson['alertSuccess'] = sprintf($GLOBALS['TL_LANG']['MSG']['successfullyBookedXSessions'], $objResource->title, $counter);
                }
            }
        }

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
            $objBooking = ResourceReservationModel::findByPk($bookingId);
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

}
