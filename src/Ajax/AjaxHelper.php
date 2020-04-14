<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Ajax;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\ResourceBookingTimeSlotModel;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\DateHelper;
use Markocupic\ResourceBookingBundle\UtcTime;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class AjaxHelper
 * @package Markocupic\ResourceBookingBundle
 */
class AjaxHelper
{

    /** @var ContaoFramework */
    private $framework;

    /** @var Security  */
    private $security;

    /** @var SessionInterface */
    private $session;

    /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag */
    public $sessionBag;

    /** @var  ResourceBookingResourceTypeModel */
    public $objSelectedResourceType;

    /** @var  ResourceBookingResourceModel */
    public $objSelectedResource;

    /** @var ModuleModel */
    public $moduleModel;

    /** @var FrontendUser */
    public $objUser;

    /**
     * AjaxHelper constructor.
     * @param ContaoFramework $framework
     * @param Security $security
     * @param SessionInterface $session
     * @param RequestStack $requestStack
     * @param string $bagName
     */
    public function __construct(ContaoFramework $framework, Security $security, SessionInterface $session, RequestStack $requestStack, string $bagName)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @throws \Exception
     */
    public function initialize()
    {
        // Set resource type
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);
        $objSelectedResourceType = $resourceBookingResourceTypeModelAdapter->findByPk($this->sessionBag->get('resType'));
        if ($objSelectedResourceType === null)
        {
            //throw new \Exception('Selected resource type not found.');
        }
        $this->objSelectedResourceType = $objSelectedResourceType;

        // Set resource
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);
        $objSelectedResource = $resourceBookingResourceModelAdapter->findByPk($this->sessionBag->get('res'));
        if ($objSelectedResource === null)
        {
            //throw new \Exception('Selected resource not found.');
        }
        $this->objSelectedResource = $objSelectedResource;

        // Set module model
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);
        $moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));
        if ($moduleModel === null)
        {
            throw new \Exception('Module model not found.');
        }
        $this->moduleModel = $moduleModel;

        /** @var FrontendUser $user */
        $objUser = $this->security->getUser();
        if (!$objUser instanceof FrontendUser)
        {
            throw new \Exception('Logged in user not found.');
        }
        $this->objUser = $objUser;
    }

    /**
     * @return array
     */
    public function fetchData(): array
    {
        $this->initialize();

        $arrData = [];

        // Load language file
        System::loadLanguageFile('default', $this->sessionBag->get('language'));

        // Handle autologout
        $arrData['opt']['autologout'] = $this->moduleModel->resourceBooking_autologout;
        $arrData['opt']['autologoutDelay'] = $this->moduleModel->resourceBooking_autologoutDelay;
        $arrData['opt']['autologoutRedirect'] = Controller::replaceInsertTags(sprintf('{{link_url::%s}}', $this->moduleModel->resourceBooking_autologoutRedirect));

        // Messages
        if ($this->objSelectedResourceType === null && !Message::hasMessages())
        {
            Message::addInfo($GLOBALS['TL_LANG']['MSG']['selectResourceTypePlease']);
        }

        if ($this->objSelectedResource === null && !Message::hasMessages())
        {
            Message::addInfo($GLOBALS['TL_LANG']['MSG']['selectResourcePlease']);
        }

        // Filter form: get resource types dropdown
        $rows = [];
        $arrResTypesIds = StringUtil::deserialize($this->moduleModel->resourceBooking_resourceTypes, true);
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
        $rows = [];
        if (($objResources = ResourceBookingResourceModel::findPublishedByPid($this->objSelectedResourceType->id)) !== null)
        {
            while ($objResources->next())
            {
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

        $objUser = $this->objUser;

        // Logged in user
        $arrData['loggedInUser'] = [
            'firstname' => $objUser->firstname,
            'lastname'  => $objUser->lastname,
            'gender'    => $GLOBALS['TL_LANG'][$objUser->gender] != '' ? $GLOBALS['TL_LANG'][$objUser->gender] : $objUser->gender,
            'email'     => $objUser->email,
            'id'        => $objUser->id,
        ];

        // Selected week
        $arrData['activeWeekTstamp'] = (int) $this->sessionBag->get('activeWeekTstamp');
        $arrData['activeWeek'] = [
            'tstampStart' => $this->sessionBag->get('activeWeekTstamp'),
            'tstampEnd'   => DateHelper::addDaysToTime(6, $this->sessionBag->get('activeWeekTstamp')),
            'dateStart'   => Date::parse(Config::get('dateFormat'), $this->sessionBag->get('activeWeekTstamp')),
            'dateEnd'     => Date::parse(Config::get('dateFormat'), DateHelper::addDaysToTime(6, $this->sessionBag->get('activeWeekTstamp'))),
            'weekNumber'  => Date::parse('W', $this->sessionBag->get('activeWeekTstamp')),
            'year'        => Date::parse('Y', $this->sessionBag->get('activeWeekTstamp')),
        ];

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('activeWeekTstamp'), DateHelper::addDaysToTime(7 * $this->sessionBag->get('intAheadWeeks')), false);

        // Send weekdays, dates and day
        $arrWeek = [];
        $arrWeekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        for ($i = 0; $i < 7; $i++)
        {
            // Skip days
            if ($this->moduleModel->resourceBooking_hideDays && !in_array($i, StringUtil::deserialize($this->moduleModel->resourceBooking_hideDaysSelection, true)))
            {
                continue;
            }
            $arrWeek[] = [
                'index'      => $i,
                'title'      => $GLOBALS['TL_LANG']['DAYS_LONG'][$i] != '' ? $GLOBALS['TL_LANG']['DAYS_LONG'][$i] : $arrWeekdays[$i],
                'titleShort' => $GLOBALS['TL_LANG']['DAYS_SHORTED'][$i] != '' ? $GLOBALS['TL_LANG']['DAYS_SHORTED'][$i] : $arrWeekdays[$i],
                'date'       => Date::parse('d.m.Y', strtotime(Date::parse('Y-m-d', $this->sessionBag->get('activeWeekTstamp')) . " +" . $i . " day"))
            ];
        }
        // Weekdays
        $arrData['weekdays'] = $arrWeek;

        $arrData['activeResourceTypeId'] = 'undefined';
        if ($this->objSelectedResourceType !== null)
        {
            $arrData['activeResourceType'] = $this->objSelectedResourceType->row();
            $arrData['activeResourceTypeId'] = $this->objSelectedResourceType->id;
        }

        // Get rows
        $arrData['activeResourceId'] = 'undefined';
        if ($this->objSelectedResource !== null && $this->objSelectedResourceType !== null)
        {
            $arrData['activeResourceId'] = $this->objSelectedResource->id;
            $arrData['activeResource'] = $this->objSelectedResource->row();

            $objSelectedResource = $this->objSelectedResource;
            $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
            $rows = [];
            $rowCount = 0;
            if ($objTimeslots !== null)
            {
                while ($objTimeslots->next())
                {
                    $cells = [];
                    $objRow = new \stdClass();

                    $cssID = sprintf('timeSlotModId_%s_%s', $this->moduleModel->id, $objTimeslots->id);
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
                        if ($this->moduleModel->resourceBooking_hideDays && !in_array($colCount, StringUtil::deserialize($this->moduleModel->resourceBooking_hideDaysSelection, true)))
                        {
                            continue;
                        }

                        $startTimestamp = strtotime(sprintf('+%s day', $colCount), $this->sessionBag->get('activeWeekTstamp')) + $objTimeslots->startTime;
                        $endTimestamp = strtotime(sprintf('+%s day', $colCount), $this->sessionBag->get('activeWeekTstamp')) + $objTimeslots->endTime;
                        $objTs = new \stdClass();
                        $objTs->index = $colCount;
                        $objTs->weekday = $arrWeekdays[$colCount];
                        $objTs->startTimeString = Date::parse('H:i', $startTimestamp);
                        $objTs->startTimestamp = (int) $startTimestamp;
                        $objTs->endTimeString = Date::parse('H:i', $endTimestamp);
                        $objTs->endTimestamp = (int) $endTimestamp;
                        $objTs->timeSpanString = Date::parse('H:i', $startTimestamp) . ' - ' . Date::parse('H:i', $endTimestamp);
                        $objTs->mondayTimestampSelectedWeek = (int) $this->sessionBag->get('activeWeekTstamp');
                        $objTs->isBooked = $this->isResourceBooked($objSelectedResource, $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->resourceId = $objSelectedResource->id;
                        $objTs->isEditable = true;
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $objTs->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTimestamp, $endTimestamp, $this->sessionBag->get('activeWeekTstamp'));
                        $objTs->bookingCheckboxId = sprintf('bookingCheckbox_modId_%s_%s_%s', $this->moduleModel->id, $rowCount, $colCount);
                        if ($objTs->isBooked)
                        {
                            $objTs->isEditable = false;
                            $objBooking = ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objSelectedResource, $startTimestamp, $endTimestamp);
                            if ($objBooking !== null)
                            {
                                if ($objBooking->member === $this->objUser->id)
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
                    $rows[] = ['cellData' => $cells, 'rowData' => $objRow];
                    $rowCount++;
                }
            }
        }
        $arrData['rows'] = $rows;

        // Get time slots
        $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($objSelectedResource->timeSlotType);
        $timeSlots = [];
        if ($objTimeslots !== null)
        {
            while ($objTimeslots->next())
            {
                $startTimestamp = (int) $objTimeslots->startTime;
                $endTimestamp = (int) $objTimeslots->endTime;
                $objTs = new \stdClass();
                $objTs->startTimeString = UtcTime::parse('H:i', $startTimestamp);
                $objTs->startTimestamp = (int) $startTimestamp;
                $objTs->endTimeString = UtcTime::parse('H:i', $endTimestamp);
                $objTs->timeSpanString = UtcTime::parse('H:i', $startTimestamp) . ' - ' . UtcTime::parse('H:i', $endTimestamp);
                $objTs->endTimestamp = (int) $endTimestamp;
                $timeSlots[] = $objTs;
            }
        }
        $arrData['timeSlots'] = $timeSlots;

        // Get messages
        $arrData['messages'] = [];
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
     * @param FrontendUser $objUser
     * @param ResourceBookingResourceModel $objResource
     * @param array $arrBookingDateSelection
     * @param int $bookingRepeatStopWeekTstamp
     * @return array
     */
    public function prepareBookingSelection(FrontendUser $objUser, ResourceBookingResourceModel $objResource, array $arrBookingDateSelection, int $bookingRepeatStopWeekTstamp): array
    {
        $this->initialize();

        $arrBookings = [];

        $objUser = FrontendUser::getInstance();

        foreach ($arrBookingDateSelection as $strTimeSlot)
        {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            $arrBooking = [
                'timeSlotId'                          => $arrTimeSlot[0],
                'startTime'                           => (int) $arrTimeSlot[1],
                'endTime'                             => (int) $arrTimeSlot[2],
                'date'                                => '',
                'datim'                               => '',
                'mondayTimestampSelectedWeek'         => (int) $arrTimeSlot[3],
                'pid'                                 => Input::post('resourceId'),
                'description'                         => Input::post('description'),
                'member'                              => $objUser->id,
                'tstamp'                              => time(),
                'resourceAlreadyBooked'               => true,
                'resourceBlocked'                     => true,
                'resourceAlreadyBookedByLoggedInUser' => false,
                'newEntry'                            => false,
                'holder'                              => ''
            ];
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

            if (!$this->isResourceBooked($objResource, $arrData['startTime'], $arrData['endTime']))
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
    public function getWeekSelection(int $startTstamp, int $endTstamp, bool $injectEmptyLine = false): array
    {
        // Load language file
        System::loadLanguageFile('default', $this->sessionBag->get('language'));

        $arrWeeks = [];

        $currentTstamp = $startTstamp;
        while ($currentTstamp <= $endTstamp)
        {
            // add empty
            if ($injectEmptyLine && DateHelper::getMondayOfCurrentWeek() == $currentTstamp)
            {
                $arrWeeks[] = [
                    'tstamp'     => '',
                    'date'       => '',
                    'optionText' => '-------------'
                ];
            }
            $tstampMonday = $currentTstamp;
            $dateMonday = Date::parse('d.m.Y', $currentTstamp);
            $tstampSunday = strtotime($dateMonday . ' + 6 days');
            $dateSunday = Date::parse('d.m.Y', $tstampSunday);
            $calWeek = Date::parse('W', $tstampMonday);
            $yearMonday = Date::parse('Y', $tstampMonday);
            $arrWeeks[] = [
                'tstamp'       => (int) $currentTstamp,
                'tstampMonday' => (int) $tstampMonday,
                'tstampSunday' => (int) $tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan'      => $dateMonday . ' - ' . $dateSunday,
                'calWeek'      => (int) $calWeek,
                'year'         => $yearMonday,
                'optionText'   => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday)
            ];

            $currentTstamp = DateHelper::addDaysToTime(7, $currentTstamp);
        }
        return $arrWeeks;
    }

    /**
     * @param int $intJumpWeek
     * @return array
     */
    public function getJumpWeekDate(int $intJumpWeek): array
    {
        $this->initialize();

        $arrReturn = [
            'disabled' => false,
            'tstamp'   => null
        ];

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = DateHelper::addDaysToTime($intJumpDays, $this->sessionBag->get('activeWeekTstamp'));
        if (!DateHelper::isValidDate($jumpTime))
        {
            $jumpTime = $this->sessionBag->get('activeWeekTstamp');
            $arrReturn['disabled'] = true;
        }

        if (!$this->sessionBag->get('activeWeekTstamp') > 0 || $this->objSelectedResourceType === null || $this->objSelectedResource === null)
        {
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = (int) $jumpTime;

        return $arrReturn;
    }

}
