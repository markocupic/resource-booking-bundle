<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
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
use Markocupic\ResourceBookingBundle\UtcTimeHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class AjaxHelper
 * @package Markocupic\ResourceBookingBundle
 */
class AjaxHelper
{

    /** @var ContaoFramework */
    private $framework;

    /** @var Security */
    private $security;

    /** @var SessionInterface */
    private $session;

    /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag */
    private $sessionBag;

    /** @var RequestStack */
    private $requestStack;

    /** @var  ResourceBookingResourceTypeModel */
    private $objSelectedResourceType;

    /** @var  ResourceBookingResourceModel */
    private $objSelectedResource;

    /** @var ModuleModel */
    private $moduleModel;

    /** @var FrontendUser */
    private $objUser;

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
        $this->sessionBag = $session->getBag($bagName);
        $this->requestStack = $requestStack;
    }

    /**
     * @throws \Exception
     */
    public function initialize()
    {
        /** @var  ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var  ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        // Set resource type
        $this->objSelectedResourceType = $resourceBookingResourceTypeModelAdapter->findByPk($this->sessionBag->get('resType'));

        // Set resource
        $this->objSelectedResource = $resourceBookingResourceModelAdapter->findByPk($this->sessionBag->get('res'));

        // Set module model
        $this->moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));
        if ($this->moduleModel === null)
        {
            throw new \Exception('Module model not found.');
        }

        $this->objUser = null;
        if ($this->security->getUser() instanceof FrontendUser)
        {
            /** @var FrontendUser $user */
            $this->objUser = $this->security->getUser();
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function fetchData(): array
    {
        $this->initialize();

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $arrData = [];

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        // Handle autologout
        if ($this->objUser)
        {
            $arrData['opt']['autologout'] = $this->moduleModel->resourceBooking_autologout;
            $arrData['opt']['autologoutDelay'] = $this->moduleModel->resourceBooking_autologoutDelay;
            $arrData['opt']['autologoutRedirect'] = $controllerAdapter->replaceInsertTags(sprintf('{{link_url::%s}}', $this->moduleModel->resourceBooking_autologoutRedirect));
        }

        // Messages
        if ($this->objSelectedResourceType === null && !$messageAdapter->hasMessages())
        {
            $messageAdapter->addInfo($GLOBALS['TL_LANG']['MSG']['selectResourceTypePlease']);
        }

        if ($this->objSelectedResource === null && !$messageAdapter->hasMessages())
        {
            $messageAdapter->addInfo($GLOBALS['TL_LANG']['MSG']['selectResourcePlease']);
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

        // Logged in user
        $arrData['userIsLoggedIn'] = false;
        if ($this->objUser !== null)
        {
            $arrData['userIsLoggedIn'] = true;
            $arrData['loggedInUser'] = [
                'firstname' => $this->objUser->firstname,
                'lastname'  => $this->objUser->lastname,
                'gender'    => $GLOBALS['TL_LANG'][$this->objUser->gender] != '' ? $GLOBALS['TL_LANG'][$this->objUser->gender] : $this->objUser->gender,
                'email'     => $this->objUser->email,
                'id'        => $this->objUser->id,
            ];
        }

        // Selected week
        $arrData['activeWeekTstamp'] = (int) $this->sessionBag->get('activeWeekTstamp');
        $arrData['activeWeek'] = [
            'tstampStart' => $this->sessionBag->get('activeWeekTstamp'),
            'tstampEnd'   => $dateHelperAdapter->addDaysToTime(6, $this->sessionBag->get('activeWeekTstamp')),
            'dateStart'   => $dateAdapter->parse(Config::get('dateFormat'), $this->sessionBag->get('activeWeekTstamp')),
            'dateEnd'     => $dateAdapter->parse(Config::get('dateFormat'), $dateHelperAdapter->addDaysToTime(6, $this->sessionBag->get('activeWeekTstamp'))),
            'weekNumber'  => $dateAdapter->parse('W', $this->sessionBag->get('activeWeekTstamp')),
            'year'        => $dateAdapter->parse('Y', $this->sessionBag->get('activeWeekTstamp')),
        ];

        // Get booking RepeatsSelection
        $arrData['bookingRepeatsSelection'] = $this->getWeekSelection((int) $this->sessionBag->get('activeWeekTstamp'), $dateHelperAdapter->addDaysToTime(7 * $this->sessionBag->get('intAheadWeeks')), false);

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
                'date'       => $dateAdapter->parse('d.m.Y', strtotime($dateAdapter->parse('Y-m-d', $this->sessionBag->get('activeWeekTstamp')) . " +" . $i . " day"))
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
        $rows = [];
        if ($this->objSelectedResource !== null && $this->objSelectedResourceType !== null)
        {
            $arrData['activeResourceId'] = $this->objSelectedResource->id;
            $arrData['activeResource'] = $this->objSelectedResource->row();

            $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($this->objSelectedResource->timeSlotType);
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
                        $objTs->startTimeString = $dateAdapter->parse('H:i', $startTimestamp);
                        $objTs->startTimestamp = (int) $startTimestamp;
                        $objTs->endTimeString = $dateAdapter->parse('H:i', $endTimestamp);
                        $objTs->endTimestamp = (int) $endTimestamp;
                        $objTs->timeSpanString = $dateAdapter->parse('H:i', $startTimestamp) . ' - ' . $dateAdapter->parse('H:i', $endTimestamp);
                        $objTs->mondayTimestampSelectedWeek = (int) $this->sessionBag->get('activeWeekTstamp');
                        $objTs->isBooked = $this->isResourceBooked($this->objSelectedResource, $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->resourceId = $this->objSelectedResource->id;
                        $objTs->isEditable = true;
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $objTs->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTimestamp, $endTimestamp, $this->sessionBag->get('activeWeekTstamp'));
                        $objTs->bookingCheckboxId = sprintf('bookingCheckbox_modId_%s_%s_%s', $this->moduleModel->id, $rowCount, $colCount);
                        if ($objTs->isBooked)
                        {
                            $objTs->isEditable = false;
                            $objBooking = ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($this->objSelectedResource, $startTimestamp, $endTimestamp);
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
        $objTimeslots = ResourceBookingTimeSlotModel::findPublishedByPid($this->objSelectedResource->timeSlotType);
        $timeSlots = [];
        if ($objTimeslots !== null)
        {
            while ($objTimeslots->next())
            {
                $startTimestamp = (int) $objTimeslots->startTime;
                $endTimestamp = (int) $objTimeslots->endTime;
                $objTs = new \stdClass();
                $objTs->startTimeString = UtcTimeHelper::parse('H:i', $startTimestamp);
                $objTs->startTimestamp = (int) $startTimestamp;
                $objTs->endTimeString = UtcTimeHelper::parse('H:i', $endTimestamp);
                $objTs->timeSpanString = UtcTimeHelper::parse('H:i', $startTimestamp) . ' - ' . UtcTimeHelper::parse('H:i', $endTimestamp);
                $objTs->endTimestamp = (int) $endTimestamp;
                $timeSlots[] = $objTs;
            }
        }
        $arrData['timeSlots'] = $timeSlots;

        // Get messages
        $arrData['messages'] = [];
        if ($messageAdapter->hasMessages())
        {
            if ($messageAdapter->hasInfo())
            {
                $arrData['messages']['info'] = $messageAdapter->generateUnwrapped('FE', true);
            }
            if ($messageAdapter->hasError())
            {
                $arrData['messages']['error'] = $messageAdapter->generateUnwrapped('FE', true);
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
     * @throws \Exception
     */
    public function prepareBookingSelection(FrontendUser $objUser, ResourceBookingResourceModel $objResource, array $arrBookingDateSelection, int $bookingRepeatStopWeekTstamp): array
    {
        $this->initialize();

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $arrBookings = [];

        $objUser = FrontendUser::getInstance();

        foreach ($arrBookingDateSelection as $strTimeSlot)
        {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            // Defaults
            $arrBooking = [
                'id'                                  => null,
                'timeSlotId'                          => $arrTimeSlot[0],
                'startTime'                           => (int) $arrTimeSlot[1],
                'endTime'                             => (int) $arrTimeSlot[2],
                'date'                                => '',
                'datim'                               => '',
                'mondayTimestampSelectedWeek'         => (int) $arrTimeSlot[3],
                'pid'                                 => Input::post('resourceId'),
                'bookingUuid'                         => '',
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
                    $arrRepeat['startTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['startTime']);
                    $arrRepeat['endTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['endTime']);
                    $arrRepeat['mondayTimestampSelectedWeek'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['mondayTimestampSelectedWeek']);
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
            $arrBookings[$index]['date'] = $dateAdapter->parse(Config::get('dateFormat'), $arrData['startTime']);
            $arrBookings[$index]['datim'] = sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $arrData['startTime']), $dateAdapter->parse(Config::get('dateFormat'), $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['endTime']));

            // Resource is unoccupied
            if (!$this->isResourceBooked($objResource, $arrData['startTime'], $arrData['endTime']))
            {
                if (($objTimeslot = ResourceBookingTimeSlotModel::findByPk($arrData['timeSlotId'])) !== null)
                {
                    $arrBookings[$index]['resourceAlreadyBooked'] = false;
                    $arrBookings[$index]['resourceBlocked'] = false;
                }
            }
            // Resource has already been booked by the current/logged in user in a previous session
            elseif (null !== ($objBooking = ResourceBookingModel::findOneByResourceIdStarttimeEndtimeAndMember($objResource, $arrData['startTime'], $arrData['endTime'], $arrData['member'])))
            {
                $arrBookings[$index]['id'] = $objBooking->id;
                $arrBookings[$index]['resourceAlreadyBooked'] = true;
                $arrBookings[$index]['resourceAlreadyBookedByLoggedInUser'] = true;
                $arrBookings[$index]['resourceBlocked'] = false;
            }
            else
            {
                // This case normally should not happen
                $arrBookings[$index]['holder'] = '';

                $objRes = ResourceBookingModel::findOneByResourceIdStarttimeAndEndtime($objResource, $arrData['startTime'], $arrData['endTime']);
                if ($objRes !== null)
                {
                    $arrBookings[$index]['holder'] = 'undefined';
                    $objMember = MemberModel::findByPk($objRes->member);
                    if ($objMember !== null)
                    {
                        $arrBookings[$index]['holder'] = $stringUtilAdapter->substr($objMember->firstname, 1, '') . '. ' . $objMember->lastname;
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
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $arrWeeks = [];

        $currentTstamp = $startTstamp;
        while ($currentTstamp <= $endTstamp)
        {
            // add empty
            if ($injectEmptyLine && $dateHelperAdapter->getMondayOfCurrentWeek() == $currentTstamp)
            {
                $arrWeeks[] = [
                    'tstamp'     => '',
                    'date'       => '',
                    'optionText' => '-------------'
                ];
            }
            $tstampMonday = $currentTstamp;
            $dateMonday = $dateAdapter->parse('d.m.Y', $currentTstamp);
            $tstampSunday = strtotime($dateMonday . ' + 6 days');
            $dateSunday = $dateAdapter->parse('d.m.Y', $tstampSunday);
            $calWeek = $dateAdapter->parse('W', $tstampMonday);
            $yearMonday = $dateAdapter->parse('Y', $tstampMonday);
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

            $currentTstamp = $dateHelperAdapter->addDaysToTime(7, $currentTstamp);
        }
        return $arrWeeks;
    }

    /**
     * @param int $intJumpWeek
     * @return array
     * @throws \Exception
     */
    public function getJumpWeekDate(int $intJumpWeek): array
    {
        $this->initialize();

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $arrReturn = [
            'disabled' => false,
            'tstamp'   => null
        ];

        $intJumpDays = 7 * $intJumpWeek;
        // Create 1 week back and 1 week ahead links
        $jumpTime = $dateHelperAdapter->addDaysToTime($intJumpDays, $this->sessionBag->get('activeWeekTstamp'));
        if (!$dateHelperAdapter->isValidDate($jumpTime))
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

    /**
     * @return ResourceBookingResourceTypeModel|null
     */
    public function getActiveResourceTypeModel(): ?ResourceBookingResourceTypeModel
    {
        /** @var  ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        return $resourceBookingResourceTypeModelAdapter->findByPk($this->sessionBag->get('resType'));
    }

    /**
     * @return ResourceBookingResourceModel|null
     */
    public function getActiveResourceModel(): ?ResourceBookingResourceModel
    {
        /** @var  ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        return $resourceBookingResourceModelAdapter->findByPk($this->sessionBag->get('res'));
    }

}
