<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Booking;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Markocupic\ResourceBookingBundle\Helper\DateHelper;
use Markocupic\ResourceBookingBundle\Helper\UtcTimeHelper;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class BookingTable.
 *
 * This is a helper class, which will generate the json response array
 * when calling the "fetchDataRequest" post ajax request from the frontend.
 */
class BookingTable
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggedInFrontendUser
     */
    private $user;

    /**
     * BookingTable constructor.
     *
     * @throws \Exception
     */
    public function __construct(ContaoFramework $framework, Security $security, SessionInterface $session, RequestStack $requestStack, LoggedInFrontendUser $user, string $bagName)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->session = $session;
        $this->sessionBag = $session->getBag($bagName);
        $this->requestStack = $requestStack;
        $this->user = $user;

        if (null === $this->getModuleModel()) {
            throw new \Exception('Module model not found.');
        }
    }

    /**
     * @throws \Exception
     */
    public function fetchData(): array
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

        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

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
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        // Messages
        if (null === $this->getActiveResourceType() && !$messageAdapter->hasMessages()) {
            $messageAdapter->addInfo($GLOBALS['TL_LANG']['MSG']['selectResourceTypePlease']);
        }

        if (null === $this->getActiveResource() && !$messageAdapter->hasMessages()) {
            $messageAdapter->addInfo($GLOBALS['TL_LANG']['MSG']['selectResourcePlease']);
        }

        // Filter form: get resource types dropdown
        $rows = [];
        $arrResTypesIds = $stringUtilAdapter->deserialize($this->getModuleModel()->resourceBooking_resourceTypes, true);

        if (null !== ($objResourceTypes = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrResTypesIds))) {
            while ($objResourceTypes->next()) {
                $rows[] = $objResourceTypes->row();
            }
            $arrData['filterBoard']['resourceTypes'] = $rows;
        }
        unset($rows);

        // Filter form: get resource dropdown
        $rows = [];

        if (null !== ($objResources = $resourceBookingResourceModelAdapter->findPublishedByPid($this->getActiveResourceType()->id))) {
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
                'gender' => '' !== $GLOBALS['TL_LANG'][$this->user->getLoggedInUser()->gender] ? $GLOBALS['TL_LANG'][$this->user->getLoggedInUser()->gender] : $this->user->getLoggedInUser()->gender,
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

        // Send weekdays, dates and day
        $arrWeek = [];
        $arrWeekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        for ($i = 0; $i < \count($arrWeekdays); ++$i) {
            // Skip days
            if ($this->getModuleModel()->resourceBooking_hideDays && !\in_array($i, $stringUtilAdapter->deserialize($this->getModuleModel()->resourceBooking_hideDaysSelection, true), false)) {
                continue;
            }
            $arrWeek[] = [
                'index' => $i,
                'title' => '' !== $GLOBALS['TL_LANG']['DAYS_LONG'][$i] ? $GLOBALS['TL_LANG']['DAYS_LONG'][$i] : $arrWeekdays[$i],
                'titleShort' => '' !== $GLOBALS['TL_LANG']['DAYS_SHORTENED'][$i] ? $GLOBALS['TL_LANG']['DAYS_SHORTENED'][$i] : $arrWeekdays[$i],
                'date' => $dateAdapter->parse('d.m.Y', strtotime($dateAdapter->parse('Y-m-d', $this->sessionBag->get('activeWeekTstamp')).' +'.$i.' day')),
            ];
        }
        // Weekdays
        $arrData['weekdays'] = $arrWeek;

        $arrData['activeResourceTypeId'] = 'undefined';

        if (null !== $this->getActiveResourceType()) {
            $arrData['activeResourceType'] = $this->getActiveResourceType()->row();
            $arrData['activeResourceTypeId'] = $this->getActiveResourceType()->id;
        }

        // Get rows
        $arrData['activeResourceId'] = 'undefined';
        $rows = [];

        if (null !== $this->getActiveResource() && null !== $this->getActiveResourceType()) {
            $arrData['activeResourceId'] = $this->getActiveResource()->id;
            $arrData['activeResource'] = $this->getActiveResource()->row();

            $objTimeslots = $resourceBookingTimeSlotModelAdapter->findPublishedByPid($this->getActiveResource()->timeSlotType);
            $rowCount = 0;

            if (null !== $objTimeslots) {
                while ($objTimeslots->next()) {
                    $cells = [];
                    $objRow = new \stdClass();

                    $cssRowId = sprintf('timeSlotModId_%s_%s', $this->getModuleModel()->id, $objTimeslots->id);
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

                    for ($colCount = 0; $colCount < 7; ++$colCount) {
                        // Skip days
                        if ($this->getModuleModel()->resourceBooking_hideDays && !\in_array($colCount, $stringUtilAdapter->deserialize($this->getModuleModel()->resourceBooking_hideDaysSelection, true), false)) {
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
                        $objTs->timeSpanString = $dateAdapter->parse('H:i', $startTimestamp).' - '.$dateAdapter->parse('H:i', $endTimestamp);
                        $objTs->mondayTimestampSelectedWeek = (int) $this->sessionBag->get('activeWeekTstamp');
                        $objTs->isBooked = $this->isResourceBooked($this->getActiveResource(), $startTimestamp, $endTimestamp);
                        $objTs->isEditable = $objTs->isBooked ? false : true;
                        $objTs->timeSlotId = $objTimeslots->id;
                        $objTs->validDate = true;
                        $objTs->resourceId = $this->getActiveResource()->id;
                        $objTs->cssClass = $cssCellClass;
                        // slotId-startTime-endTime-mondayTimestampSelectedWeek
                        $objTs->bookingCheckboxValue = sprintf('%s-%s-%s-%s', $objTimeslots->id, $startTimestamp, $endTimestamp, $this->sessionBag->get('activeWeekTstamp'));
                        $objTs->bookingCheckboxId = sprintf('bookingCheckbox_modId_%s_%s_%s', $this->getModuleModel()->id, $rowCount, $colCount);

                        if ($objTs->isBooked) {
                            $objTs->isEditable = false;
                            $objBooking = $resourceBookingModelAdapter->findOneByResourceIdStarttimeAndEndtime($this->getActiveResource(), $startTimestamp, $endTimestamp);

                            if (null !== $objBooking) {
                                if ($objBooking->member === $this->user->getLoggedInUser()->id) {
                                    $objTs->isEditable = true;
                                    $objTs->isHolder = true;
                                }

                                // Presets
                                $objTs->bookedByFirstname = '';
                                $objTs->bookedByLastname = '';
                                $objTs->bookedByFullname = '';

                                $arrFields = $stringUtilAdapter->deserialize($this->getModuleModel()->resourceBooking_clientPersonalData, true);

                                $objMember = $memberModelAdapter->findByPk($objBooking->member);

                                if (null !== $objMember) {
                                    // Do not transmit and display sensitive data if user is not holder
                                    if (!$objTs->isHolder && $this->getModuleModel()->resourceBooking_displayClientPersonalData && !empty($arrFields)) {
                                        foreach ($arrFields as $fieldname) {
                                            $objTs->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($objMember->$fieldname);
                                        }

                                        if (\in_array('firstname', $arrFields, true) && \in_array('lastname', $arrFields, true)) {
                                            $objTs->bookedByFullname = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                        }
                                    } else {
                                        foreach (array_keys($objMember->row()) as $fieldname) {
                                            $varData = $objMember->$fieldname;

                                            if ('id' === $fieldname || 'password' === $fieldname) {
                                                continue;
                                            }

                                            // Convert bin uuids to string uuids
                                            if (!empty($varData) && !preg_match('//u', (string) $varData)) {
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

                                            $objTs->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($varData);
                                            $objTs->{'bookedBy'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($varData);
                                        }
                                        $objTs->bookedByFullname = $stringUtilAdapter->decodeEntities($objMember->firstname.' '.$objMember->lastname);
                                    }
                                }

                                // Send sensitive data if it has been permitted in tl_module
                                if ($this->getModuleModel()->resourceBooking_setBookingSubmittedFields) {
                                    $arrFields = $stringUtilAdapter->deserialize($this->getModuleModel()->resourceBooking_bookingSubmittedFields, true);

                                    foreach ($arrFields as $fieldname) {
                                        if (\in_array($fieldname, $arrFields, true)) {
                                            $objTs->{'booking'.ucfirst($fieldname)} = $stringUtilAdapter->decodeEntities($objBooking->$fieldname);
                                        }
                                    }
                                }

                                $objTs->bookingId = $objBooking->id;
                                $objTs->bookingUuid = $objBooking->bookingUuid;
                            }
                        }

                        // Do not allow editing if resourceBooking_addDateStop is set and resourceBooking_dateStop < time()
                        if ($this->getModuleModel()->resourceBooking_addDateStop) {
                            if ($objTs->endTimestamp > $this->getModuleModel()->resourceBooking_dateStop + 24 * 3600) {
                                $objTs->isEditable = false;
                                $objTs->validDate = false;
                            }
                        }

                        // Do not allow editing, if time slot lies in the past
                        if ($objTs->endTimestamp < strtotime('today')) {
                            $objTs->isEditable = false;
                        }

                        $cells[] = $objTs;
                    }
                    $rows[] = ['cellData' => $cells, 'rowData' => $objRow];
                    ++$rowCount;
                }
            }
        }

        $arrData['rows'] = $rows;

        // Get time slots
        $objTimeslots = $resourceBookingTimeSlotModelAdapter->findPublishedByPid($this->getActiveResource()->timeSlotType);
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
                $startTimestamp = (int) $objTimeslots->startTime;
                $endTimestamp = (int) $objTimeslots->endTime;
                $objTs = new \stdClass();
                $objTs->cssClass = $cssCellClass;
                $objTs->startTimeString = $utcTimeHelperAdapter->parse('H:i', $startTimestamp);
                $objTs->startTimestamp = (int) $startTimestamp;
                $objTs->endTimeString = $utcTimeHelperAdapter->parse('H:i', $endTimestamp);
                $objTs->timeSpanString = $utcTimeHelperAdapter->parse('H:i', $startTimestamp).' - '.$utcTimeHelperAdapter->parse('H:i', $endTimestamp);
                $objTs->endTimestamp = (int) $endTimestamp;
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

    protected function isResourceBooked(ResourceBookingResourceModel $objResource, int $slotStartTime, int $slotEndTime): bool
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        if (null === $resourceBookingModelAdapter->findOneByResourceIdStarttimeAndEndtime($objResource, $slotStartTime, $slotEndTime)) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    protected function getWeekSelection(int $startTstamp, int $endTstamp, bool $injectEmptyLine = false): array
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

        while ($currentTstamp <= $endTstamp) {
            $cssClass = 'past-week';

            // add empty
            if ($dateHelperAdapter->getMondayOfCurrentWeek() === $currentTstamp) {
                if ($injectEmptyLine) {
                    $arrWeeks[] = [
                        'tstamp' => '',
                        'date' => '',
                        'optionText' => '-------------',
                    ];
                }

                $cssClass = 'current-week';
            }

            if ($dateHelperAdapter->getMondayOfCurrentWeek() < $currentTstamp) {
                $cssClass = 'future-week';
            }

            $tstampMonday = $currentTstamp;
            $dateMonday = $dateAdapter->parse('d.m.Y', $currentTstamp);
            $tstampSunday = strtotime($dateMonday.' + 6 days');
            $dateSunday = $dateAdapter->parse('d.m.Y', $tstampSunday);
            $calWeek = $dateAdapter->parse('W', $tstampMonday);
            $yearMonday = $dateAdapter->parse('Y', $tstampMonday);
            $arrWeeks[] = [
                'cssClass' => $cssClass,
                'tstamp' => (int) $currentTstamp,
                'tstampMonday' => (int) $tstampMonday,
                'tstampSunday' => (int) $tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan' => $dateMonday.' - '.$dateSunday,
                'calWeek' => (int) $calWeek,
                'year' => $yearMonday,
                'optionDateStart' => $dateMonday,
                'optionDateEnd' => $dateSunday,
                'optionText' => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday),
            ];

            $currentTstamp = $dateHelperAdapter->addDaysToTime(7, $currentTstamp);
        }

        return $arrWeeks;
    }

    /**
     * @throws \Exception
     */
    protected function getJumpWeekDate(int $intJumpWeek): array
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

        if (!$dateHelperAdapter->isValidDate($jumpTime)) {
            $jumpTime = $this->sessionBag->get('activeWeekTstamp');
            $arrReturn['disabled'] = true;
        }

        if (!$this->sessionBag->get('activeWeekTstamp') > 0 || null === $this->getActiveResourceType() || null === $this->getActiveResource()) {
            $arrReturn['disabled'] = true;
        }

        $arrReturn['tstamp'] = (int) $jumpTime;

        return $arrReturn;
    }

    /**
     * @throws \Exception
     */
    protected function getActiveResource(): ?ResourceBookingResourceModel
    {
        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        return $resourceBookingResourceModelAdapter->findByPk($this->sessionBag->get('res'));
    }

    /**
     * @throws \Exception
     */
    protected function getActiveResourceType(): ?ResourceBookingResourceTypeModel
    {
        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        return $resourceBookingResourceTypeModelAdapter->findByPk($this->sessionBag->get('resType'));
    }

    /**
     * @throws \Exception
     */
    protected function getModuleModel(): ?ModuleModel
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        return $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));
    }
}
