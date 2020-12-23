<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventSubscriber;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\FrontendUser;
use Contao\System;
use Markocupic\ResourceBookingBundle\Booking\Booking;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Helper\AjaxHelper;
use Markocupic\ResourceBookingBundle\Helper\DateHelper;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class AjaxRequestEventSubscriber.
 */
class AjaxRequestEventSubscriber
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Security
     */
    private $ajaxHelper;

    /**
     * @var Booking
     */
    private $booking;

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
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var FrontendUser
     */
    private $objUser;

    /**
     * AjaxRequestEventSubscriber constructor.
     */
    public function __construct(ContaoFramework $framework, AjaxHelper $ajaxHelper, Booking $booking, SessionInterface $session, RequestStack $requestStack, string $bagName, Security $security, EventDispatcherInterface $eventDispatcher)
    {
        $this->framework = $framework;
        $this->ajaxHelper = $ajaxHelper;
        $this->booking = $booking;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->sessionBag = $session->getBag($bagName);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
        $this->objUser = null;

        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $user */
            $this->objUser = $this->security->getUser();
        }
    }

    public function onXmlHttpRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isXmlHttpRequest()) {
            $action = $request->request->get('action', null);

            if (null !== $action) {
                if (\is_callable([self::class, 'on'.ucfirst($action)])) {
                    $this->{'on'.ucfirst($action)}($ajaxRequestEvent);
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function onFetchDataRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->ajaxHelper->fetchData());
    }

    /**
     * @throws \Exception
     */
    protected function onApplyFilterRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $request = $this->requestStack->getCurrentRequest();

        // Get resource type from post request
        $intResType = (int) $request->request->get('resType', 0);

        if (null !== $resourceBookingResourceTypeModelAdapter->findByPk($intResType)) {
            $this->sessionBag->set('resType', $intResType);
        } else {
            $this->sessionBag->set('resType', 0);
        }

        // Get resource from post request
        $intRes = (int) $request->request->get('res', 0);

        if (0 === $this->sessionBag->get('resType')) {
            // Set resource to 0, if there is no resource type selected
            $intRes = 0;
        }

        // Check if res exists
        $invalidRes = true;

        if (null !== ($objRes = $resourceBookingResourceModelAdapter->findByPk($intRes))) {
            // ... and if res is in the current resType container
            if ((int) $objRes->pid === (int) $intResType) {
                $this->sessionBag->set('res', $intRes);
                $invalidRes = false;
            }
        }

        // Set res to 0, if the res is invalid
        if ($invalidRes) {
            $this->sessionBag->set('res', 0);
        }

        // Get active week timestamp from post request
        $intTstampDate = (int) $request->request->get('date', 0);
        $intTstampDate = $dateHelperAdapter->isValidDate($intTstampDate) ? $intTstampDate : $dateHelperAdapter->getMondayOfCurrentWeek();

        // Validate $intTstampDate
        $tstampFirstPossibleWeek = $this->sessionBag->get('tstampFirstPossibleWeek');

        if ($intTstampDate < $tstampFirstPossibleWeek) {
            $intTstampDate = $tstampFirstPossibleWeek;
        }

        $tstampLastPossibleWeek = $this->sessionBag->get('tstampLastPossibleWeek');

        if ($intTstampDate > $tstampLastPossibleWeek) {
            $intTstampDate = $tstampLastPossibleWeek;
        }

        $this->sessionBag->set('activeWeekTstamp', (int) $intTstampDate);

        // Fetch data and send it to the browser
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->ajaxHelper->fetchData());
    }

    /**
     * @throws \Exception
     */
    protected function onJumpWeekRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $this->onApplyFilterRequest($ajaxRequestEvent);
    }

    /**
     * @throws \Exception
     */
    protected function onBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $this->booking->initialize();

        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        // First we check, if booking is possible!
        if (!$this->booking->isBookingPossible()) {
            $ajaxResponse->setErrorMessage($this->booking->getErrorMessage());
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

            return;
        }

        $objBookings = $this->booking->getBookingCollection();

        // Dispatch pre booking event
        $objPreBookingEvent = new PreBookingEvent();
        $objPreBookingEvent->setUser($this->objUser);
        $objPreBookingEvent->setBookingCollection($objBookings);
        $objPreBookingEvent->setSessionBag($this->sessionBag);
        $this->eventDispatcher->dispatch($objPreBookingEvent, 'rbb.event.pre_booking');

        if (null !== $objBookings) {
            while ($objBookings->next()) {
                $objBooking = $objBookings->current();
                $objBooking->save();

                // Log
                $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                $strLog = sprintf('New resource "%s" (with ID %s) has been booked.', $this->booking->getResource()->title, $objBooking->id);
                $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
            }
        }

        // Dispatch post booking event
        $objBookings = $resourceBookingModelAdapter->findByBookingUuid($this->booking->getBookingUuid());

        if (null !== $objBookings) {
            $objPostBookingEvent = new PostBookingEvent();
            $objPostBookingEvent->setUser($this->objUser);
            $objPostBookingEvent->setBookingCollection($objBookings);
            $objPostBookingEvent->setSessionBag($this->sessionBag);
            $this->eventDispatcher->dispatch($objPostBookingEvent, 'rbb.event.post_booking');
        }

        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setSuccessMessage(
            sprintf(
                $GLOBALS['TL_LANG']['MSG']['successfullyBookedXItems'],
                $this->booking->getResource()->title,
                \count($this->booking->getBookingArray())
            )
        );

        // Add booking selection to response
        $ajaxResponse->setData('bookingSelection', $this->booking->getBookingArray());
    }

    /**
     * @throws \Exception
     */
    protected function onBookingFormValidationRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        $this->booking->initialize();

        $hasError = false;
        $arrBookings = [];

        $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        $ajaxResponse->setData('noDatesSelected', false);
        $ajaxResponse->setData('resourceIsAlreadyBooked', false);
        $ajaxResponse->setData('passedValidation', false);
        $ajaxResponse->setData('noBookingRepeatStopWeekTstampSelected', false);
        $ajaxResponse->setData('message', null);

        if (!$hasError) {
            $ajaxResponse->setData('passedValidation', true);

            $arrBookings = $this->booking->getBookingArray();

            if (!$this->booking->isBookingPossible()) {
                if (empty($arrBookings)) {
                    $ajaxResponse->setData('passedValidation', false);
                    $ajaxResponse->setData('noDatesSelected', true);
                } else {
                    foreach ($arrBookings as $arrBooking) {
                        if (true === $arrBooking['invalidDate']) {
                            $ajaxResponse->setData('passedValidation', false);
                            $ajaxResponse->setData('dateNotInAllowedTimeSpan', true);
                        }

                        if (true === $arrBooking['resourceIsAlreadyBooked'] && false === $arrBooking['resourceIsAlreadyBookedByLoggedInUser']) {
                            $ajaxResponse->setData('passedValidation', false);
                            $ajaxResponse->setData('resourceIsAlreadyBooked', true);
                        }
                    }
                }
            }
        }

        $ajaxResponse->setData('bookingSelection', $arrBookings);
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
    }

    /**
     * @throws \Exception
     */
    protected function onCancelBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $request = $this->requestStack->getCurrentRequest();

        $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

        if (null !== $this->objUser && $request->request->get('bookingId') > 0) {
            $bookingId = $request->request->get('bookingId');
            $objBooking = $resourceBookingModelAdapter->findByPk($bookingId);

            if (null !== $objBooking) {
                if ($objBooking->member === $this->objUser->id) {
                    $intId = $objBooking->id;
                    $bookingUuid = $objBooking->bookingUuid;
                    $timeSlotId = $objBooking->timeSlotId;
                    $weekday = $dateAdapter->parse('D', $objBooking->startTime);
                    $resourceTitle = '';

                    if (null !== ($objBookingResource = $objBooking->getRelated('pid'))) {
                        $resourceTitle = $objBookingResource->title;
                    }

                    $strLog = sprintf('Resource booking for "%s" (with ID %s) has been deleted.', $resourceTitle, $intId);

                    // Delete entry
                    $intAffected = $objBooking->delete();

                    if ($intAffected) {
                        // Log
                        $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                    }

                    $countRepetitionsToDelete = 0;

                    // Delete repetitions with same bookingUuid and same starttime and endtime
                    if ('true' === $request->request->get('deleteBookingsWithSameBookingUuid')) {
                        $arrColumns = [
                            'tl_resource_booking.bookingUuid=?',
                            'tl_resource_booking.timeSlotId=?',
                        ];
                        $arrValues = [
                            $bookingUuid,
                            $timeSlotId,
                        ];
                        $objRepetitions = $resourceBookingModelAdapter->findBy($arrColumns, $arrValues);

                        if (null !== $objRepetitions) {
                            while ($objRepetitions->next()) {
                                if ($dateAdapter->parse('D', $objRepetitions->startTime) === $weekday) {
                                    $intIdRepetition = $objRepetitions->id;

                                    $resourceTitle = '';

                                    if (null !== ($objBookingResource = $objRepetitions->getRelated('pid'))) {
                                        $resourceTitle = $objBookingResource->title;
                                    }

                                    $strLog = sprintf('Resource Booking for "%s" (with ID %s) has been deleted.', $resourceTitle, $intIdRepetition);
                                    $objRepetitions->delete();

                                    // Log
                                    $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');

                                    if ($logger) {
                                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                                    }
                                    ++$countRepetitionsToDelete;
                                }
                            }
                        }
                    }
                    // End delete repetitions

                    $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

                    if ('true' === $request->request->get('deleteBookingsWithSameBookingUuid')) {
                        $ajaxResponse->setSuccessMessage(sprintf($GLOBALS['TL_LANG']['MSG']['successfullyCanceledBookingAndItsRepetitions'], $intId, $countRepetitionsToDelete));
                    } else {
                        $ajaxResponse->setSuccessMessage(sprintf($GLOBALS['TL_LANG']['MSG']['successfullyCanceledBooking'], $intId));
                    }
                } else {
                    $ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['notAllowedToCancelBooking']);
                }
            } else {
                $ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['notAllowedToCancelBooking']);
            }
        }
    }
}
