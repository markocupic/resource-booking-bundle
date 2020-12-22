<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Booking;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\Helper\DateHelper;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class Booking.
 */
class Booking
{
    /**
     * @var string
     */
    public $bookingUuid;

    /**
     * @var ModuleModel|null
     */
    public $moduleModel;

    /**
     * @var ResourceBookingResourceModel
     */
    public $objResource;

    /**
     * @var array
     */
    public $arrBookingDateSelection = [];

    /**
     * @var int
     */
    public $bookingRepeatStopWeekTstamp = 0;

    /**
     * @var int
     */
    public $selectedSlots = 0;

    /**
     * @var Collection
     */
    public $bookingCollection;

    /**
     * @var array
     */
    public $arrBookingCollection = [];

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var FrontendUser
     */
    private $objUser;

    /**
     * @var int
     */
    private $intError = 0;

    /**
     * @var string|null
     */
    private $errorMessage;

    /**
     * Booking constructor.
     *
     * @throws \Exception
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RequestStack $requestStack, string $bagName, Security $security)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->sessionBag = $session->getBag($bagName);
        $this->security = $security;

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $user */
            $this->objUser = $this->security->getUser();
        } else {
            throw new \Exception('No logged in user found.');
        }

        // Set module model
        $this->moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));

        if (null === $this->moduleModel) {
            throw new \Exception('Module model not found.');
        }

        $this->bookingUuid = $stringUtilAdapter->binToUuid($databaseAdapter->getInstance()->getUuid());
    }

    public function hasErrors(): bool
    {
        return $this->intError > 0;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @throws \Exception
     */
    public function buildBookingCollection(): bool
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        $request = $this->requestStack->getCurrentRequest();

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $this->objResource = $resourceBookingResourceModelAdapter->findPublishedByPk($request->request->get('resourceId'));

        if (null === $this->objResource) {
            ++$this->intError;
            $this->setErrorMessage('No resource found');

            return false;
        }

        $this->arrBookingDateSelection = !empty($request->request->get('bookingDateSelection')) ? $request->request->get('bookingDateSelection') : [];

        if (!\is_array($this->arrBookingDateSelection) || empty($this->arrBookingDateSelection)) {
            ++$this->intError;
            $this->setErrorMessage($GLOBALS['TL_LANG']['MSG']['selectBookingDatesPlease']);

            return false;
        }

        $this->bookingRepeatStopWeekTstamp = $request->request->get('bookingRepeatStopWeekTstamp');

        if (!$this->bookingRepeatStopWeekTstamp > 0) {
            ++$this->intError;
            $this->setErrorMessage('Booking repeat stop week timestamp must be greater then 0.');

            return false;
        }

        if (null === $this->objUser || null === $this->objResource || !$this->bookingRepeatStopWeekTstamp > 0 || !\is_array($this->arrBookingDateSelection)) {
            ++$this->intError;
            $this->setErrorMessage($GLOBALS['TL_LANG']['MSG']['generalBookingError']);

            return false;
        }

        // Prepare $arrBookings with the helper method
        if (!$this->_buildBookingCollection()) {
            return false;
        }

        return true;
    }

    public function isResourceBooked(ResourceBookingResourceModel $objResource, int $slotStartTime, int $slotEndTime): bool
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        if (null === $resourceBookingModelAdapter->findOneByResourceIdStarttimeAndEndtime($objResource, $slotStartTime, $slotEndTime)) {
            return false;
        }

        return true;
    }

    protected function setErrorMessage(string $msg): void
    {
        $this->errorMessage = $msg;
    }

    /**
     * @throws \Exception
     */
    protected function _buildBookingCollection(): bool
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var ResourceBookingTimeSlotModel $resourceBookingTimeSlotModelAdapter */
        $resourceBookingTimeSlotModelAdapter = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class);

        $arrBookings = [];

        foreach ($this->arrBookingDateSelection as $strTimeSlot) {
            // slotId-startTime-endTime-mondayTimestampSelectedWeek
            $arrTimeSlot = explode('-', $strTimeSlot);
            // Defaults
            $arrBooking = [
                'id' => null,
                'timeSlotId' => $arrTimeSlot[0],
                'startTime' => (int) $arrTimeSlot[1],
                'endTime' => (int) $arrTimeSlot[2],
                'date' => '',
                'datim' => '',
                'mondayTimestampSelectedWeek' => (int) $arrTimeSlot[3],
                'pid' => $inputAdapter->post('resourceId'),
                'bookingUuid' => '',
                'description' => $inputAdapter->post('description'),
                'member' => $this->objUser->id,
                'tstamp' => time(),
                'resourceIsAlreadyBooked' => true,
                'resourceBlocked' => true,
                'invalidDate' => false,
                'resourceIsAlreadyBookedByLoggedInUser' => false,
                'newEntry' => false,
                'holder' => '',
            ];
            $arrBookings[] = $arrBooking;

            // Handle repetitions
            if ($arrTimeSlot[3] < $this->bookingRepeatStopWeekTstamp) {
                $doRepeat = true;

                while (true === $doRepeat) {
                    $arrRepeat = $arrBooking;
                    $arrRepeat['startTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['startTime']);
                    $arrRepeat['endTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['endTime']);
                    $arrRepeat['mondayTimestampSelectedWeek'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['mondayTimestampSelectedWeek']);
                    $arrBookings[] = $arrRepeat;
                    // Stop repeating
                    if ($arrRepeat['mondayTimestampSelectedWeek'] >= $this->bookingRepeatStopWeekTstamp) {
                        $doRepeat = false;
                    }
                    $arrBooking = $arrRepeat;
                    unset($arrRepeat);
                }
            }
        }

        if (!empty($arrBookings)) {
            // Sort array by startTime
            usort(
                $arrBookings,
                static function ($a, $b) {
                    return $a['startTime'] <=> $b['startTime'];
                }
            );
        }

        // Check if booking is possible
        foreach ($arrBookings as $index => $arrData) {
            // Set date
            $arrBookings[$index]['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $arrData['startTime']);
            $arrBookings[$index]['datim'] = sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $arrData['startTime']), $dateAdapter->parse($configAdapter->get('dateFormat'), $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['endTime']));

            // Invalid time period
            if ($this->moduleModel->resourceBooking_addDateStop && $this->moduleModel->resourceBooking_dateStop + 24 * 3600 < $arrData['endTime']) {
                $arrBookings[$index]['resourceBlocked'] = true;
                $arrBookings[$index]['invalidDate'] = true;
            } // All ok! Resource is bookable. -> override defaults
            elseif (null !== $resourceBookingTimeSlotModelAdapter->findByPk($arrData['timeSlotId']) && !$this->isResourceBooked($this->objResource, $arrData['startTime'], $arrData['endTime'])) {
                $arrBookings[$index]['resourceBlocked'] = false;
                $arrBookings[$index]['resourceIsAlreadyBooked'] = false;
            } // Resource has already been booked by the current/logged in user in a previous session
            elseif (null !== ($objBooking = $resourceBookingModelAdapter->findOneByResourceIdStarttimeEndtimeAndMember($this->objResource, $arrData['startTime'], $arrData['endTime'], $arrData['member']))) {
                $arrBookings[$index]['resourceBlocked'] = false;
                $arrBookings[$index]['resourceIsAlreadyBooked'] = true;
                $arrBookings[$index]['resourceIsAlreadyBookedByLoggedInUser'] = true;
                $arrBookings[$index]['id'] = $objBooking->id;
            } else { // This case normally should not happen
                $arrBookings[$index]['resourceBlocked'] = true;
                $arrBookings[$index]['resourceIsAlreadyBooked'] = true;

                $arrBookings[$index]['holder'] = '';

                $objRes = $resourceBookingModelAdapter->findOneByResourceIdStarttimeAndEndtime($this->objResource, $arrData['startTime'], $arrData['endTime']);

                if (null !== $objRes) {
                    $arrBookings[$index]['holder'] = 'undefined';
                    $objMember = $memberModelAdapter->findByPk($objRes->member);

                    if (null !== $objMember) {
                        $arrBookings[$index]['holder'] = $stringUtilAdapter->substr($objMember->firstname, 1, '').'. '.$objMember->lastname;
                    }
                }
            }
        }

        foreach ($arrBookings as $arrBooking) {
            if ($arrBooking['resourceIsAlreadyBooked'] && false === $arrBooking['resourceIsAlreadyBookedByLoggedInUser']) {
                ++$this->intError;
                $this->setErrorMessage($GLOBALS['TL_LANG']['MSG']['resourceIsAlreadyBooked']);
            }
        }

        // Build collection
        $bookingCollection = [];

        foreach ($arrBookings as $i => $arrBooking) {
            // Set title
            $arrBooking['title'] = sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->objResource->title,
                $GLOBALS['TL_LANG']['MSC']['bookingFor'],
                $this->objUser->firstname,
                $this->objUser->lastname,
                $dateAdapter->parse($configAdapter->get('datimFormat'), $arrBooking['startTime']),
                $dateAdapter->parse($configAdapter->get('datimFormat'), $arrBooking['endTime'])
            );

            if (true === $arrBooking['resourceIsAlreadyBookedByLoggedInUser'] && null !== $arrBooking['id']) {
                $objBooking = $resourceBookingModelAdapter->findByPk($arrBooking['id']);
            } else {
                $objBooking = new ResourceBookingModel();
            }

            if (null !== $objBooking) {
                $arrBooking['bookingUuid'] = $this->bookingUuid;

                foreach ($arrBooking as $k => $v) {
                    $objBooking->{$k} = $v;
                }
                $bookingCollection[] = $objBooking;
                $arrBookings[$i]['newEntry'] = true;
            }

            ++$this->selectedSlots;
        }

        if (0 === $this->selectedSlots) {
            ++$this->intError;
            $this->setErrorMessage($GLOBALS['TL_LANG']['MSG']['noItemsBooked']);

            return false;
        }

        $this->arrBookingCollection = $arrBookings;
        $this->bookingCollection = new Collection($bookingCollection, 'tl_resource_booking');

        if ($this->hasErrors()) {
            return false;
        }

        return true;
    }
}
