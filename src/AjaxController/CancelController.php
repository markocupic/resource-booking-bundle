<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\System;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostCancelingEvent;
use Markocupic\ResourceBookingBundle\Event\PreCancelingEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class CancelController.
 */
final class CancelController extends AbstractController implements ControllerInterface
{
    private TranslatorInterface $translator;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(TranslatorInterface $translator, EventDispatcherInterface $eventDispatcher): void
    {
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());

        $request = $this->requestStack->getCurrentRequest();

        $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

        $arrIds = [];

        $blnError = false;
        $errorMsg = $this->translator->trans('RBB.ERR.somethingWentWrong', [], 'contao_default');

        if (null === $this->user->getLoggedInUser() || !(int) $request->request->get('id') > 0) {
            $blnError = true;
            $errorMsg = $this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default');
        } else {
            $id = $request->request->get('id');
            $objBooking = $resourceBookingModelAdapter->findByPk($id);

            if (null === $objBooking) {
                $blnError = true;
                $errorMsg = $this->translator->trans('RBB.ERR.bookingNotFound', [$id], 'contao_default');
            } else {
                if ((int) $objBooking->member !== (int) $this->user->getLoggedInUser()->id) {
                    $blnError = true;
                    $errorMsg = $this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default');
                } else {
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

                    // Delete repetitions with same bookingUuid and same start time and end time
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

                    if ('true' === $request->request->get('deleteBookingsWithSameBookingUuid')) {
                        $ajaxResponse->setConfirmationMessage(
                            $this->translator->trans(
                                'RBB.MSG.successfullyCanceledBookingAndItsRepetitions',
                                [$intId, $countRepetitionsToDelete],
                                'contao_default',
                            )
                        );
                    } else {
                        $ajaxResponse->setConfirmationMessage(
                            $this->translator->trans(
                                'RBB.MSG.successfullyCanceledBooking',
                                [$intId],
                                'contao_default',
                            )
                        );
                    }
                }
            }
        }

        if (!$blnError) {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', true);
        } else {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', false);
            $ajaxResponse->setErrorMessage($errorMsg);
        }
    }
}
