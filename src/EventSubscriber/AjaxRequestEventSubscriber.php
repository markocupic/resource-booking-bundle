<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventSubscriber;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\Model\Collection;
use Contao\System;
use Markocupic\ResourceBookingBundle\Booking\BookingMain;
use Markocupic\ResourceBookingBundle\Booking\BookingWindow;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PostCancelingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreCancelingEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class AjaxRequestEventSubscriber.
 */
final class AjaxRequestEventSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 1000;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var BookingMain
     */
    private $bookingMain;

    /**
     * @var BookingWindow
     */
    private $bookingWindow;

    /**
     * @var LoggedInFrontendUser
     */
    private $user;

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
     * AjaxRequestEventSubscriber constructor.
     */
    public function __construct(ContaoFramework $framework, BookingMain $bookingMain, BookingWindow $bookingWindow, LoggedInFrontendUser $user, SessionInterface $session, RequestStack $requestStack, string $bagName, Security $security, EventDispatcherInterface $eventDispatcher)
    {
        $this->framework = $framework;
        $this->bookingMain = $bookingMain;
        $this->bookingWindow = $bookingWindow;
        $this->user = $user;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->sessionBag = $session->getBag($bagName);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AjaxRequestEvent::NAME => ['onXmlHttpRequest', self::PRIORITY],
        ];
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
    private function onFetchDataRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->bookingMain->fetchData());
    }

    /**
     * @throws \Exception
     */
    private function onApplyFilterRequest(AjaxRequestEvent $ajaxRequestEvent): void
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
        $ajaxResponse->setDataFromArray($this->bookingMain->fetchData());
    }

    /**
     * @throws \Exception
     */
    private function onJumpWeekRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $this->onApplyFilterRequest($ajaxRequestEvent);
    }

    /**
     * @throws \Exception
     */
    private function onBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->sessionBag->get('language'));

        $this->bookingWindow->initialize();

        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        // First we check, if booking is possible!
        if (!$this->bookingWindow->isBookingPossible()) {
            $ajaxResponse->setErrorMessage($this->bookingWindow->getErrorMessage());
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

            return;
        }

        $objBookings = $this->bookingWindow->getBookingCollection();

        if (null !== $objBookings) {
            $objBookings->reset();
        }

        // Dispatch pre booking event "rbb.event.pre_booking"
        $eventData = new \stdClass();
        $eventData->user = $this->user->getLoggedInUser();
        $eventData->bookingCollection = $objBookings;
        $eventData->ajaxResponse = $ajaxResponse;
        $eventData->sessionBag = $this->sessionBag;
        // Dispatch event
        $objPreBookingEvent = new PreBookingEvent($eventData);
        $this->eventDispatcher->dispatch($objPreBookingEvent, PreBookingEvent::NAME);

        if (null !== $objBookings) {
            $objBookings->reset();
        }

        if (null !== $objBookings) {
            while ($objBookings->next()) {
                $objBooking = $objBookings->current();

                // Save booking
                if (!$objBooking->doNotSave) {
                    $objBooking->save();

                    // Log
                    $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                    $strLog = sprintf('New resource "%s" (with ID %s) has been booked.', $this->bookingWindow->getActiveResource()->title, $objBooking->id);
                    $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                }
            }
        }

        // Dispatch post booking event "rbb.event.post_booking"
        /** @var Collection $objBookings */
        $objBookings = $resourceBookingModelAdapter->findByBookingUuid($this->bookingWindow->getBookingUuid());

        if (null !== $objBookings) {
            $eventData = new \stdClass();
            $eventData->user = $this->user->getLoggedInUser();
            $eventData->bookingCollection = $objBookings;
            $eventData->ajaxResponse = $ajaxResponse;
            $eventData->sessionBag = $this->sessionBag;
            // Dispatch event
            $objPostBookingEvent = new PostBookingEvent($eventData);
            $this->eventDispatcher->dispatch($objPostBookingEvent, PostBookingEvent::NAME);
        }

        if (null !== $objBookings) {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

            if (null === $ajaxResponse->getConfirmationMessage()) {
                $ajaxResponse->setConfirmationMessage(
                    sprintf(
                        $GLOBALS['TL_LANG']['MSG']['successfullyBookedXItems'],
                        $this->bookingWindow->getActiveResource()->title,
                        $objBookings->count()
                    )
                );
            }
        } else {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

            if (null === $ajaxResponse->getErrorMessage()) {
                $ajaxResponse->setErrorMessage($GLOBALS['TL_LANG']['MSG']['generalBookingError']);
            }
        }

        // Add booking selection to response
        if (null !== $objBookings) {
            $objBookings->reset();
        }

        $ajaxResponse->setData('bookingSelection', $objBookings ? $objBookings->fetchAll() : []);
    }

    /**
     * @throws \Exception
     */
    private function onBookingFormValidationRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        $this->bookingWindow->initialize();

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

            $arrBookings = $this->bookingWindow->getBookingCollection()->fetchAll();

            if (!$this->bookingWindow->isBookingPossible()) {
                if (empty($arrBookings)) {
                    $ajaxResponse->setData('passedValidation', false);
                    $ajaxResponse->setData('noDatesSelected', true);
                } else {
                    foreach ($arrBookings as $arrBooking) {
                        if (true === $arrBooking['invalidDate']) {
                            $ajaxResponse->setData('passedValidation', false);
                            $ajaxResponse->setData('dateNotInAllowedTimeSpan', true);
                            break;
                        }

                        if (true === $arrBooking['resourceIsAlreadyBooked'] && false === $arrBooking['resourceIsAlreadyBookedByLoggedInUser']) {
                            $ajaxResponse->setData('passedValidation', false);
                            $ajaxResponse->setData('resourceIsAlreadyBooked', true);
                            $ajaxResponse->setData('resourceBlocked', true);
                            break;
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
    private function onCancelBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
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

        $arrIds = [];

        if (null !== $this->user->getLoggedInUser() && $request->request->get('bookingId') > 0) {
            $bookingId = $request->request->get('bookingId');
            $objBooking = $resourceBookingModelAdapter->findByPk($bookingId);

            if (null !== $objBooking) {
                if ($objBooking->member === $this->user->getLoggedInUser()->id) {
                    $intId = $objBooking->id;
                    $bookingUuid = $objBooking->bookingUuid;
                    $timeSlotId = $objBooking->timeSlotId;
                    $weekday = $dateAdapter->parse('D', $objBooking->startTime);
                    $resourceTitle = '';

                    if (null !== ($objBookingResource = $objBooking->getRelated('pid'))) {
                        $resourceTitle = $objBookingResource->title;
                    }

                    $arrIds[] = $objBooking->id;

                    $countRepetitionsToDelete = 0;

                    // Delete repetitions with same bookingUuid and same starttime and endtime
                    if ('true' === $request->request->get('deleteBookingsWithSameBookingUuid')) {
                        $arrColumns = [
                            'tl_resource_booking.bookingUuid=?',
                            'tl_resource_booking.timeSlotId=?',
                            'tl_resource_booking.id!=?',
                            'tl_resource_booking.member=?',
                        ];

                        $arrValues = [
                            $bookingUuid,
                            $timeSlotId,
                            $objBooking->id,
                            $this->user->getLoggedInUser()->id,
                        ];

                        $objRepetitions = $resourceBookingModelAdapter->findBy($arrColumns, $arrValues);

                        if (null !== $objRepetitions) {
                            while ($objRepetitions->next()) {
                                if ($dateAdapter->parse('D', $objRepetitions->startTime) === $weekday) {
                                    $arrIds[] = $objRepetitions->id;
                                    ++$countRepetitionsToDelete;
                                }
                            }
                        }
                    }

                    if (null !== ($objBookingRemove = $resourceBookingModelAdapter->findByIds($arrIds))) {
                        // Dispatch pre canceling event "rbb.event.pre_canceling"
                        $eventData = new \stdClass();
                        $eventData->user = $this->user->getLoggedInUser();
                        $eventData->bookingCollection = $objBookingRemove;
                        $eventData->sessionBag = $this->sessionBag;
                        // Dispatch event
                        $objPreCancelingEvent = new PreCancelingEvent($eventData);
                        $this->eventDispatcher->dispatch($objPreCancelingEvent, PreCancelingEvent::NAME);

                        while ($objBookingRemove->next()) {
                            // Use pre canceling subscriber to prevent canceling
                            // by setting $objBookingRemove->doNotCancel to true
                            if (!$objBookingRemove->doNotCancel) {
                                $intAffected = $objBookingRemove->delete();

                                if ($intAffected) {
                                    // Log
                                    $strLog = sprintf('Resource Booking for "%s" (with ID %s) has been deleted.', $resourceTitle, $objBookingRemove->id);
                                    $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');

                                    if ($logger) {
                                        $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                                    }
                                }
                            }
                        }

                        // Dispatch post canceling event "rbb.event.post_canceling"
                        $eventData = new \stdClass();
                        $eventData->user = $this->user->getLoggedInUser();
                        $eventData->bookingCollection = $objBookingRemove;
                        $eventData->sessionBag = $this->sessionBag;
                        // Dispatch event
                        $objPostCancelingEvent = new PostCancelingEvent($eventData);
                        $this->eventDispatcher->dispatch($objPostCancelingEvent, PostCancelingEvent::NAME);
                    }

                    $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

                    if ('true' === $request->request->get('deleteBookingsWithSameBookingUuid')) {
                        $ajaxResponse->setConfirmationMessage(sprintf($GLOBALS['TL_LANG']['MSG']['successfullyCanceledBookingAndItsRepetitions'], $intId, $countRepetitionsToDelete));
                    } else {
                        $ajaxResponse->setConfirmationMessage(sprintf($GLOBALS['TL_LANG']['MSG']['successfullyCanceledBooking'], $intId));
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
