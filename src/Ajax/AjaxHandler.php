<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Ajax;

use Contao\Config;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\System;
use Markocupic\ResourceBookingBundle\DateHelper;
use Psr\Log\LogLevel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class AjaxHandler
 * @package Markocupic\ResourceBookingBundle
 */
class AjaxHandler
{

    /** @var ContaoFramework */
    private $framework;

    /** @var Security */
    private $ajaxHelper;

    /** @var SessionInterface */
    private $session;

    /** @var RequestStack */
    private $requestStack;

    /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag */
    private $sessionBag;

    /**
     * AjaxHandler constructor.
     * @param ContaoFramework $framework
     * @param AjaxHelper $ajaxHelper
     * @param SessionInterface $session
     * @param RequestStack $requestStack
     * @param string $bagName
     */
    public function __construct(ContaoFramework $framework, AjaxHelper $ajaxHelper, SessionInterface $session, RequestStack $requestStack, string $bagName)
    {
        $this->framework = $framework;
        $this->ajaxHelper = $ajaxHelper;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @return array
     */
    public function fetchDataRequest(): array
    {
        $arrJson = [];
        $arrJson['data'] = $this->ajaxHelper->fetchData();
        $arrJson['status'] = 'success';
        return $arrJson;
    }

    /**
     * @return array
     */
    public function sendApplyFilterRequest(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get resource type from post request
        $intResType = (int) $request->request->get('resType', 0);

        /** @var  ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);
        if ($resourceBookingResourceTypeModelAdapter->findByPk($intResType) !== null)
        {
            $this->sessionBag->set('resType', $intResType);
        }

        // Get resource from post request
        $intRes = (int) $request->request->get('res', 0);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);
        if ($resourceBookingResourceModelAdapter->findByPk($intRes) !== null)
        {
            $this->sessionBag->set('res', $intRes);
        }

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        // Get active week timestamp from post request
        $intTstampDate = (int) $request->request->get('date', 0);
        $intTstampDate = $dateHelperAdapter->isValidDate($intTstampDate) ? $intTstampDate : $dateHelperAdapter->getMondayOfCurrentWeek();

        // Validate $intTstampDate
        $tstampFirstPossibleWeek = $this->sessionBag->get('tstampFirstPossibleWeek');
        if ($intTstampDate < $tstampFirstPossibleWeek)
        {
            $intTstampDate = $tstampFirstPossibleWeek;
        }

        $tstampLastPossibleWeek = $this->sessionBag->get('tstampLastPossibleWeek');
        if ($intTstampDate > $tstampLastPossibleWeek)
        {
            $intTstampDate = $tstampLastPossibleWeek;
        }

        $this->sessionBag->set('activeWeekTstamp', (int) $intTstampDate);

        // Fetch data and send it to the browser
        $arrJson = [];
        $arrJson['data'] = $this->ajaxHelper->fetchData();
        $arrJson['status'] = 'success';
        return $arrJson;
    }

    /**
     * @return array
     */
    public function sendJumpWeekRequest(): array
    {
        return $this->sendApplyFilterRequest();
    }

    /**
     * @return array
     */
    public function sendBookingRequest(): array
    {
        // Load language file
        System::loadLanguageFile('default', $this->sessionBag->get('language'));

        $arrJson = [];
        $arrJson['status'] = 'error';
        $errors = 0;
        $arrBookings = [];
        $intResourceId = Input::post('resourceId');
        $objResource = ResourceBookingResourceModel::findPublishedByPk($intResourceId);
        $arrBookingDateSelection = !empty(Input::post('bookingDateSelection')) ? Input::post('bookingDateSelection') : [];

        $bookingRepeatStopWeekTstamp = Input::post('bookingRepeatStopWeekTstamp');
        $counter = 0;

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
            $arrBookings = $this->ajaxHelper->prepareBookingSelection($objUser, $objResource, $arrBookingDateSelection, (int) $bookingRepeatStopWeekTstamp);

            foreach ($arrBookings as $arrBooking)
            {
                if ($arrBooking['resourceAlreadyBooked'] && $arrBooking['resourceAlreadyBookedByLoggedInUser'] === false)
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
                    if ($arrBooking['resourceAlreadyBookedByLoggedInUser'] === false)
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

                        // Log
                        $logger = System::getContainer()->get('monolog.logger.contao');
                        $strLog = sprintf('New resource with ID %s has been booked.', $objBooking->id);
                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
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

        return $arrJson;
    }

    /**
     * @return array
     */
    public function sendBookingFormValidationRequest(): array
    {
        // Load language file
        System::loadLanguageFile('default', $this->sessionBag->get('language'));

        $arrJson = [];
        $arrJson['status'] = 'error';
        $arrJson['bookingFormValidation'] = [
            'noDatesSelected'         => false,
            'resourceIsAlreadyBooked' => false,
            'passedValidation'        => false,
        ];

        $errors = 0;
        $counter = 0;
        $blnBookingsPossible = true;
        $arrBookings = [];
        $intResourceId = Input::post('resourceId');
        $objResource = ResourceBookingResourceModel::findPublishedByPk($intResourceId);
        $arrBookingDateSelection = !empty(Input::post('bookingDateSelection')) ? Input::post('bookingDateSelection') : [];
        $bookingRepeatStopWeekTstamp = Input::post('bookingRepeatStopWeekTstamp');

        if (!FE_USER_LOGGED_IN || $objResource === null || !$bookingRepeatStopWeekTstamp > 0)
        {
            $errors++;
            $arrJson['alertError'] = $GLOBALS['TL_LANG']['MSG']['generalBookingError'];
        }

        if ($errors === 0)
        {
            $objUser = FrontendUser::getInstance();

            // Prepare $arrBookings with the helper method
            $ajaxHelper = System::getContainer()->get('Markocupic\ResourceBookingBundle\Ajax\AjaxHelper');
            $arrBookings = $ajaxHelper->prepareBookingSelection($objUser, $objResource, $arrBookingDateSelection, (int) $bookingRepeatStopWeekTstamp);

            foreach ($arrBookings as $arrBooking)
            {
                if ($arrBooking['resourceAlreadyBooked'] === true && $arrBooking['resourceAlreadyBookedByLoggedInUser'] === false)
                {
                    $blnBookingsPossible = false;
                }
                $counter++;
            }

            if ($counter === 0)
            {
                $arrJson['bookingFormValidation']['passedValidation'] = false;
                $arrJson['bookingFormValidation']['noDatesSelected'] = true;
            }
            elseif (!$blnBookingsPossible)
            {
                $arrJson['bookingFormValidation']['passedValidation'] = false;
                $arrJson['bookingFormValidation']['resourceIsAlreadyBooked'] = true;
            }
            else // All ok!
            {
                $arrJson['bookingFormValidation']['passedValidation'] = true;
            }
        }

        // Return $arrBookings
        $arrJson['bookingFormValidation']['bookingSelection'] = $arrBookings;

        return ['data' => $arrJson['bookingFormValidation'], 'status' => 'success'];
    }

    /**
     * @return array
     */
    public function sendCancelBookingRequest(): array
    {
        // Load language file
        System::loadLanguageFile('default', $this->sessionBag->get('language'));

        $arrJson = [];
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
                    $intId = $objBooking->id;
                    // Delete entry
                    $intAffected = $objBooking->delete();
                    if ($intAffected)
                    {
                        // Log
                        $logger = System::getContainer()->get('monolog.logger.contao');
                        $strLog = sprintf('Resource Booking with ID %s has been deleted.', $intId);
                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                    }

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

        return $arrJson;
    }

    /**
     * @return array
     */
    public function sendIsOnlineRequest(): array
    {
        $arrJson = [];
        $arrJson['status'] = 'success';
        $arrJson['isOnline'] = 'true';
        return $arrJson;
    }



}

