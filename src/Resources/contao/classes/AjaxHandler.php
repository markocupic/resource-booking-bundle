<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 08.05.2019
 * Time: 13:10
 */

namespace Markocupic\ResourceReservationBundle;

use Contao\Date;
use Contao\ResourceReservationTimeSlotModel;
use Symfony\Component\HttpFoundation\JsonResponse;

class AjaxHandler
{
    public function getDataAll($objModule)
    {
        $arrItems = array();
        $arrJson = array();

        $arrJson['status'] = 'success';
        $arrJson['intSelectedDate'] = $objModule->intSelectedDate;
        // Send weekdays
        $arrWeekdays = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $arrJson['weekdays'] = array_map(function ($el) {
            return $GLOBALS['TL_LANG']['MSC'][$el] != '' ? $GLOBALS['TL_LANG']['MSC'][$el] : $el;
        }, $arrWeekdays);

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
                        $objTs->isBooked = false;
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
                $objTs->startTimeString = ResourceReservationHelper::parseToUtcDate('H:i', $startTimestamp);
                $objTs->startTimestamp = $startTimestamp;
                $objTs->endTimeString = ResourceReservationHelper::parseToUtcDate('H:i', $endTimestamp);
                $objTs->endTimestamp = $endTimestamp;
                $objTs->isBooked = false;
                $timeSlots[] = $objTs;
            }
        }
        $arrJson['timeSlots'] = $timeSlots;

        $response = new JsonResponse($arrJson);
        return $response->send();
    }
}
