<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Config;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\ResourceBookingModel;
use Contao\ResourceBookingResourceModel;
use Contao\System;
use Markocupic\ResourceBookingBundle\Runtime\Runtime;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class AjaxHandler
 * @package Markocupic\ResourceBookingBundle
 */
class AjaxHandler
{
    /**
     * @param Runtime $objRuntime
     * @return array
     */
    public function fetchDataRequest(Runtime $objRuntime): array
    {
        $arrJson = [];
        $arrJson['data'] = ResourceBookingHelper::fetchData($objRuntime);
        $arrJson['status'] = 'success';
        return $arrJson;
    }

    /**
     * @param Runtime $objRuntime
     * @return array
     */
    public function sendApplyFilterRequest(Runtime $objRuntime): array
    {
        $arrJson = [];
        $arrJson['data'] = ResourceBookingHelper::fetchData($objRuntime);
        $arrJson['status'] = 'success';
        return $arrJson;
    }

    /**
     * @param Runtime $objRuntime
     */
    public function sendJumpWeekRequest(Runtime $objRuntime): array
    {
        return $this->sendApplyFilterRequest($objRuntime);
    }

    /**
     * @param Runtime $objRuntime
     * @return array
     */
    public function sendBookingRequest(Runtime $objRuntime): array
    {
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
            $arrBookings = ResourceBookingHelper::prepareBookingSelection($objRuntime, $objUser, $objResource, $arrBookingDateSelection, (int) $bookingRepeatStopWeekTstamp);

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
     * @param Runtime $objRuntime
     * @return array
     */
    public function sendBookingFormValidationRequest(Runtime $objRuntime): array
    {
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
            $arrBookings = ResourceBookingHelper::prepareBookingSelection($objRuntime, $objUser, $objResource, $arrBookingDateSelection, (int) $bookingRepeatStopWeekTstamp);

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
     * @param Runtime $objRuntime
     * @return array
     */
    public function sendCancelBookingRequest(Runtime $objRuntime): array
    {
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
     * @param Runtime $objRuntime
     * @return array
     */
    public function sendIsOnlineRequest(Runtime $objRuntime): array
    {
        $arrJson = [];
        $arrJson['status'] = 'success';
        $arrJson['isOnline'] = 'true';
        return $arrJson;
    }

    /**
     * @param Runtime $objRuntime
     */
    public static function sendLogoutRequest(Runtime $objRuntime): void
    {
        // Unset session
        /** @var  \Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
        $objSession = System::getContainer()->get('session');
        $bagName = System::getContainer()->getParameter('resource_booking_bundle.session.attribute_bag_name');
        /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag $session */
        $session = $objSession->getBag($bagName);
        $session->clear();

        // Unset cookie
        $cookie_name = 'PHPSESSID';
        unset($_COOKIE[$cookie_name]);
        // Empty value and expiration one hour before
        $res = setcookie($cookie_name, '', time() - 3600);
        // Logout user
        throw new RedirectResponseException(System::getContainer()->get('security.logout_url_generator')->getLogoutUrl());
    }

}
