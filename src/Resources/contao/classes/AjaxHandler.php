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
                'title' => $GLOBALS['TL_LANG']['MSC'][$arrWeekdays[$i]] != '' ? $GLOBALS['TL_LANG']['MSC'][$arrWeekdays[$i]] : $arrWeekdays[$i],
                'date'  => Date::parse('d.m.Y', strtotime(Date::parse('Y-m-d', $objModule->intSelectedDate) . " +" . $i . " day"))
            );
        }

        $arrJson['weekdays'] = $arrWeek;

        // Get rows
        if ($objModule->objSelectedResource !== null)
        {
            $objSelectedResource = $objModule->objSelectedResource;
            $objTimeslots = ResourceReservationTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
            $rows = array();
            if ($objTimeslots !== null)
            {
                while ($objTimeslots->next())
                {
                    $cells = array();
                    for ($i = 0; $i < 7; $i++)
                    {
                        $startTimestamp = strtotime(sprintf('+%s day', $i), $objModule->intSelectedDate) + $objTimeslots->startTime;
                        $endTimestamp = strtotime(sprintf('+%s day', $i), $objModule->intSelectedDate) + $objTimeslots->endTime;

                        $objTs = new \stdClass();
                        $objTs->weekday = $arrWeekdays[$i];
                        $objTs->startTimeString = Date::parse('H:i', $startTimestamp);
                        $objTs->startTimestamp = $startTimestamp;
                        $objTs->endTimeString = Date::parse('H:i', $endTimestamp);
                        $objTs->endTimestamp = $endTimestamp;
                        $objTs->isBooked = ResourceReservationHelper::isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->resourceId = $objSelectedResource->id;
                        $objTs->isEditable = true;

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
                            }
                        }

                        $cells[] = $objTs;
                    }
                    $rows[] = $cells;
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
                $objTs->weekday = $arrWeekdays[$i];
                $objTs->startTimeString = UtcDate::parse('H:i', $startTimestamp);
                $objTs->startTimestamp = $startTimestamp;
                $objTs->endTimeString = UtcDate::parse('H:i', $endTimestamp);
                $objTs->endTimestamp = $endTimestamp;
                $objTs->isBooked = ResourceReservationHelper::isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);

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
        if (FE_USER_LOGGED_IN && Input::post('resourceId') > 0 && Input::post('startTime') > 0 && Input::post('endTime') > 0)
        {
            $resourceId = Input::post('resourceId');
            $descr = Input::post('description');
            $startTime = Input::post('startTime');
            $endTime = Input::post('endTime');
            $objUser = FrontendUser::getInstance();

            $objResource = ResourceReservationResourceModel::findByPk(Input::post('resourceId'));
            if ($objResource !== null)
            {
                if (!ResourceReservationHelper::isResourceBooked($objResource, $startTime, $endTime))
                {
                    $objReservation = new ResourceReservationModel();
                    $objReservation->member = $objUser->id;
                    $objReservation->firstname = $objUser->firstname;
                    $objReservation->lastname = $objUser->lastname;
                    $objReservation->startTime = $startTime;
                    $objReservation->endTime = $endTime;
                    $objReservation->description = $descr;
                    $objReservation->pid = $objResource->id;
                    $objReservation->tstamp = time();
                    $objReservation->title = $objResource->title . ': Booking for ' . $objUser->firstname . ' ' . $objUser->lastname;

                    $objReservation->save();
                    $arrJson['status'] = 'success';
                    $arrJson['alertSuccess'] = sprintf($GLOBALS['TL_LANG']['MSG']['successfullyBooked'], $objResource->title);
                }
                else
                {
                    $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['resourceAlreadyBooked'];
                }
            }
            else
            {
                $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['noResourceSelected'];
            }
        }

        $response = new JsonResponse($arrJson);
        return $response->send();
    }
}
