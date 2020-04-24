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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Date;
use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\Date\DateHelper;
use Psr\Log\LogLevel;
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

    /** @var AjaxResponse */
    private $ajaxResponse;

    /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag */
    private $sessionBag;

    /** @var Security */
    private $security;

    /** @var FrontendUser */
    private $objUser;

    /**
     * AjaxHandler constructor.
     * @param ContaoFramework $framework
     * @param AjaxHelper $ajaxHelper
     * @param SessionInterface $session
     * @param RequestStack $requestStack
     * @param AjaxResponse $ajaxResponse
     * @param string $bagName
     * @param Security $security
     */
    public function __construct(ContaoFramework $framework, AjaxHelper $ajaxHelper, SessionInterface $session, RequestStack $requestStack, AjaxResponse $ajaxResponse, string $bagName, Security $security)
    {
        $this->framework = $framework;
        $this->ajaxHelper = $ajaxHelper;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->ajaxResponse = $ajaxResponse;
        $this->sessionBag = $session->getBag($bagName);
        $this->security = $security;
        $this->objUser = null;
        if ($this->security->getUser() instanceof FrontendUser)
        {
            /** @var FrontendUser $user */
            $this->objUser = $this->security->getUser();
        }
    }

    /**
     * @return AjaxResponse
     * @throws \Exception
     */
    public function fetchDataRequest(): AjaxResponse
    {
        $this->ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $this->ajaxResponse->setDataFromArray($this->ajaxHelper->fetchData());
        return $this->ajaxResponse;
    }

    /**
     * @return AjaxResponse
     * @throws \Exception
     */
    public function applyFilterRequest(): AjaxResponse
    {
        /** @var  ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $request = $this->requestStack->getCurrentRequest();

        // Get resource type from post request
        $intResType = (int) $request->request->get('resType', 0);

        if ($resourceBookingResourceTypeModelAdapter->findByPk($intResType) !== null)
        {
            $this->sessionBag->set('resType', $intResType);
        }
        else
        {
            $this->sessionBag->set('resType', 0);
        }

        // Get resource from post request
        $intRes = (int) $request->request->get('res', 0);
        if ($this->sessionBag->get('resType') === 0)
        {
            // Set resource to 0, if there is no resource type selected
            $intRes = 0;
        }
        if ($resourceBookingResourceModelAdapter->findByPk($intRes) !== null)
        {
            $this->sessionBag->set('res', $intRes);
        }
        else
        {
            $this->sessionBag->set('res', 0);
        }

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
        $this->ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $this->ajaxResponse->setDataFromArray($this->ajaxHelper->fetchData());
        return $this->ajaxResponse;
    }

    /**
     * @return AjaxResponse
     * @throws \Exception
     */
    public function jumpWeekRequest(): AjaxResponse
    {
        return $this->applyFilterRequest();
    }

    /**
     * @return AjaxResponse
     * @throws \Exception
     */
    public function bookingRequest(): AjaxResponse
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $request = $this->requestStack->getCurrentRequest();

        $this->ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

        $errors = 0;
        $arrBookings = [];
        $intResourceId = $request->request->get('resourceId');
        $objResource = $resourceBookingResourceModelAdapter->findPublishedByPk($intResourceId);
        $arrBookingDateSelection = !empty($request->request->get('bookingDateSelection')) ? $request->request->get('bookingDateSelection') : [];

        $bookingRepeatStopWeekTstamp = $request->request->get('bookingRepeatStopWeekTstamp');
        $selectedSlots = 0;

        if ($this->objUser === null || $objResource === null || !$bookingRepeatStopWeekTstamp > 0 || !is_array($arrBookingDateSelection))
        {
            $errors++;
            $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['generalBookingError']);
        }

        if (empty($arrBookingDateSelection))
        {
            $errors++;
            $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['selectBookingDatesPlease']);
        }

        if (!$errors)
        {
            // Set a unique booking id
            $bookingUuid = $stringUtilAdapter->binToUuid($databaseAdapter->getInstance()->getUuid());

            // Prepare $arrBookings with the helper method
            $arrBookings = $this->ajaxHelper->prepareBookingSelection($this->objUser, $objResource, $arrBookingDateSelection, (int) $bookingRepeatStopWeekTstamp);

            foreach ($arrBookings as $arrBooking)
            {
                if ($arrBooking['resourceAlreadyBooked'] && $arrBooking['resourceAlreadyBookedByLoggedInUser'] === false)
                {
                    $errors++;
                }
            }

            if ($errors)
            {
                $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['resourceAlreadyBooked']);
            }
            else
            {
                foreach ($arrBookings as $i => $arrBooking)
                {
                    // Set title
                    $arrBooking['title'] = sprintf('%s : %s %s %s [%s - %s]', $objResource->title, $GLOBALS['TL_LANG']['MSC']['bookingFor'], $this->objUser->firstname, $this->objUser->lastname, $dateAdapter->parse($configAdapter->get('datimFormat'), $arrBooking['startTime']), $dateAdapter->parse(Config::get('datimFormat'), $arrBooking['endTime']));
                    if ($arrBooking['resourceAlreadyBookedByLoggedInUser'] === true && null !== $arrBooking['id'])
                    {
                        $objBooking = $resourceBookingModelAdapter->findByPk($arrBooking['id']);
                    }
                    else
                    {
                        $objBooking = new ResourceBookingModel();
                    }
                    if ($objBooking !== null)
                    {
                        $arrBooking['bookingUuid'] = $bookingUuid;
                        foreach ($arrBooking as $k => $v)
                        {
                            $objBooking->{$k} = $v;
                        }
                        $objBooking->save();
                        $arrBookings[$i]['newEntry'] = true;

                        // Log
                        $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                        $strLog = sprintf('New resource with ID %s has been booked.', $objBooking->id);
                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                    }

                    $selectedSlots++;
                }

                if (!$selectedSlots)
                {
                    $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['noItemsBooked']);
                }
                else
                {
                    $objBookingCollection = $resourceBookingModelAdapter->findByBookingUuid($bookingUuid);
                    if ($objBookingCollection !== null)
                    {
                        // HOOK: add custom logic
                        if (isset($GLOBALS['TL_HOOKS']['resourceBookingPostBooking']) && \is_array($GLOBALS['TL_HOOKS']['resourceBookingPostBooking']))
                        {
                            foreach ($GLOBALS['TL_HOOKS']['resourceBookingPostBooking'] as $callback)
                            {
                                $systemAdapter->importStatic($callback[0])->{$callback[1]}($objBookingCollection, $this->requestStack->getCurrentRequest(), $this->objUser, $this);
                            }
                        }
                    }

                    $this->ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
                    $this->ajaxResponse->setSuccessMessage(sprintf($GLOBALS['TL_LANG']['MSG']['successfullyBookedXItems'], $objResource->title, $selectedSlots));
                }
            }
        }

        // Add booking selection to response
        $this->ajaxResponse->setData('bookingSelection', $arrBookings);
        return $this->ajaxResponse;
    }

    /**
     * @return AjaxResponse
     * @throws \Exception
     */
    public function bookingFormValidationRequest(): AjaxResponse
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        $request = $this->requestStack->getCurrentRequest();

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $this->ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        $this->ajaxResponse->setData('noDatesSelected', false);
        $this->ajaxResponse->setData('resourceIsAlreadyBooked', false);
        $this->ajaxResponse->setData('passedValidation', false);
        $this->ajaxResponse->setData('message', null);

        $errors = 0;
        $selectedSlots = 0;
        $blnBookingsPossible = true;
        $arrBookings = [];
        $objResource = $resourceBookingResourceModelAdapter->findPublishedByPk($request->request->get('resourceId', 0));
        $arrBookingDateSelection = !empty($request->request->get('bookingDateSelection')) && \is_array($request->request->get('bookingDateSelection')) ? $request->request->get('bookingDateSelection') : [];
        $bookingRepeatStopWeekTstamp = (int) $request->request->get('bookingRepeatStopWeekTstamp', 0);

        if ($this->objUser === null || $objResource === null || !$bookingRepeatStopWeekTstamp > 0)
        {
            $errors++;
            $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['generalBookingError']);
        }

        if (!$errors)
        {
            // Prepare $arrBookings with the helper method
            $arrBookings = $this->ajaxHelper->prepareBookingSelection($this->objUser, $objResource, $arrBookingDateSelection, (int) $bookingRepeatStopWeekTstamp);

            foreach ($arrBookings as $arrBooking)
            {
                if ($arrBooking['resourceAlreadyBooked'] === true && $arrBooking['resourceAlreadyBookedByLoggedInUser'] === false)
                {
                    $blnBookingsPossible = false;
                }
                $selectedSlots++;
            }

            if (!$selectedSlots)
            {
                $this->ajaxResponse->setData('passedValidation', false);
                $this->ajaxResponse->setData('noDatesSelected', true);
            }
            elseif (!$blnBookingsPossible)
            {
                $this->ajaxResponse->setData('passedValidation', false);
                $this->ajaxResponse->setData('resourceIsAlreadyBooked', true);
            }
            else // All ok!
            {
                $this->ajaxResponse->setData('passedValidation', true);
            }
        }

        // Return $arrBookings
        $this->ajaxResponse->setData('bookingSelection', $arrBookings);
        $this->ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

        return $this->ajaxResponse;
    }

    /**
     * @return AjaxResponse
     * @throws \Exception
     */
    public function cancelBookingRequest(): AjaxResponse
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        $request = $this->requestStack->getCurrentRequest();

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $this->ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

        if ($this->objUser !== null && $request->request->get('bookingId') > 0)
        {
            $bookingId = $request->request->get('bookingId');
            $objBooking = ResourceBookingModel::findByPk($bookingId);
            if ($objBooking !== null)
            {
                if ($objBooking->member === $this->objUser->id)
                {
                    $intId = $objBooking->id;
                    // Delete entry
                    $intAffected = $objBooking->delete();
                    if ($intAffected)
                    {
                        // Log
                        $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                        $strLog = sprintf('Resource Booking with ID %s has been deleted.', $intId);
                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                    }

                    $this->ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
                    $this->ajaxResponse->setSuccessMessage($GLOBALS['TL_LANG']['MSG']['successfullyCanceledBooking']);
                }
                else
                {
                    $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['notAllowedToCancelBooking']);
                }
            }
            else
            {
                $this->ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['notAllowedToCancelBooking']);
            }
        }

        return $this->ajaxResponse;
    }

}

