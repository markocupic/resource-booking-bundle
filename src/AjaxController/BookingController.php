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

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotBooking;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class AjaxRequestEventSubscriber.
 */
final class BookingController extends AbstractController implements ControllerInterface
{
    private Utils $utils;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function setController(Utils $utils, EventDispatcherInterface $eventDispatcher): void
    {
        $this->utils = $utils;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent): void
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());

        $this->initialize();

        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        // First we check, if booking is possible!
        if (!$this->isBookingPossible()) {
            $ajaxResponse->setErrorMessage(
                $this->translator->trans(
                    $this->getErrorMessage(),
                    [],
                    'contao_default'
                )
            );
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

            return;
        }

        $objBookings = $this->getBookingCollection();

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
        $this->eventDispatcher->dispatch($objPreBookingEvent);

        if (null !== $objBookings) {
            $objBookings->reset();
        }

        if (null !== $objBookings) {
            while ($objBookings->next()) {
                $objBooking = $objBookings->current();

                // Check if mandatory fields are filled out, see dca mandatory key
                if (true !== ($success = $this->utils->areMandatoryFieldsSet($objBooking->row(), 'tl_resource_booking'))) {
                    throw new \Exception('No value detected for the mandatory field '.$success);
                }

                // Save booking
                if (!$objBooking->doNotSave) {
                    $objBooking->save();

                    // Log
                    $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                    $strLog = sprintf('New resource "%s" (with ID %s) has been booked.', $this->getActiveResource()->title, $objBooking->id);
                    $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                }
            }
            $ajaxResponse->setData('bookingSucceeded', true);
        }

        // Dispatch post booking event "rbb.event.post_booking"
        /** @var Collection $objBookings */
        $objBookings = $resourceBookingModelAdapter->findByBookingUuid($this->getBookingUuid());

        if (null !== $objBookings) {
            $eventData = new \stdClass();
            $eventData->user = $this->user->getLoggedInUser();
            $eventData->bookingCollection = $objBookings;
            $eventData->ajaxResponse = $ajaxResponse;
            $eventData->sessionBag = $this->sessionBag;
            // Dispatch event
            $objPostBookingEvent = new PostBookingEvent($eventData);
            $this->eventDispatcher->dispatch($objPostBookingEvent);
        }

        if (null !== $objBookings) {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

            if (null === $ajaxResponse->getConfirmationMessage()) {
                $ajaxResponse->setConfirmationMessage(
                    $this->translator->trans(
                        'RBB.MSG.successfullyBookedXItems',
                        [$this->getActiveResource()->title, $objBookings->count()],
                        'contao_default'
                    )
                );
            }
        } else {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);

            if (null === $ajaxResponse->getErrorMessage()) {
                $ajaxResponse->setErrorMessage(
                    $this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default')
                );
            }
        }

        // Add booking selection to response
        if (null !== $objBookings) {
            $objBookings->reset();
        }

        $ajaxResponse->setData('bookingSelection', $objBookings ? $objBookings->fetchAll() : []);
    }

    private function getBookingUuid(): string
    {
        if (!$this->bookingUuid) {
            /** @var StringUtil $stringUtilAdapter */
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

            /** @var Database $databaseAdapter */
            $databaseAdapter = $this->framework->getAdapter(Database::class);

            $this->bookingUuid = $stringUtilAdapter->binToUuid($databaseAdapter->getInstance()->getUuid());
        }

        return $this->bookingUuid;
    }

    /**
     * @throws \Exception
     */
    private function isBookingPossible(): bool
    {
        $objBookings = $this->getBookingCollection();
        $objBookings->reset();

        $arrBookedSlots = $objBookings->fetchAll();

        if (!\is_array($arrBookedSlots) || empty($arrBookedSlots)) {
            return false;
        }

        foreach ($arrBookedSlots as $arrBooking) {
            if (!$arrBooking['isBookable']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    private function getBookingCollection(): Collection
    {
        if (null === $this->bookingCollection) {
            $this->setBookingCollectionFromRequest();
        }

        return $this->bookingCollection;
    }

    /**
     * @throws \Exception
     */
    private function setBookingCollectionFromRequest(): void
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

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $request = $this->requestStack->getCurrentRequest();

        $arrBookedSlots = [];

        $this->arrDateSelection = !empty($request->request->get('bookingDateSelection')) ? $request->request->get('bookingDateSelection') : [];

        if (!empty($this->arrDateSelection) && \is_array($this->arrDateSelection)) {
            foreach ($this->arrDateSelection as $strTimeSlot) {
                // slotId-startTime-endTime-mondayTimestampSelectedWeek
                $arrTimeSlot = explode('-', $strTimeSlot);
                // Defaults
                $arrData = [
                    'timeSlotId' => $arrTimeSlot[0],
                    'startTime' => (int) $arrTimeSlot[1],
                    'endTime' => (int) $arrTimeSlot[2],
                    'date' => '',
                    'datim' => '',
                    'title' => '',
                    'mondayTimestampSelectedWeek' => (int) $arrTimeSlot[3],
                    'pid' => $inputAdapter->post('resourceId'),
                    'itemsBooked' => $inputAdapter->post('itemsBooked'),
                    'description' => $stringUtilAdapter->decodeEntities($inputAdapter->post('bookingDescription')),
                    'member' => $this->user->getLoggedInUser()->id,
                    'tstamp' => time(),
                    'isBookable' => false,
                    'enoughItemsAvailable' => false,
                    'isFullyBooked' => false,
                    'isValidDate' => true,
                    'hasBookings' => false,
                    'bookings' => null,
                    'userHasBooked' => false,
                    'bookingRelatedToLoggedInUser' => null,
                ];

                // Load dca
                $controllerAdapter->loadDataContainer('tl_resource_booking');
                $arrDca = $GLOBALS['TL_DCA']['tl_resource_booking'];
                $arrAllowed = array_keys($arrDca['fields']);
                $arrAllowed[] = 'bookingDateSelection[]';
                $arrAllowed[] = 'bookingRepeatStopWeekTstamp';

                // Add data from POST, thus the extension can easily be extended
                foreach (array_keys($_POST) as $k) {
                    if (!\in_array($k, $arrAllowed, true)) {
                        continue;
                    }

                    if (!isset($arrData[$k])) {
                        $arrData[$k] = true === $arrDca['fields'][$k]['eval']['decodeEntities'] ? $stringUtilAdapter->decodeEntities($inputAdapter->post($k)) : $inputAdapter->post($k);
                    }
                }

                $arrBookedSlots[] = $arrData;

                // Handle repetitions
                if ($arrTimeSlot[3] < $this->bookingRepeatStopWeekTstamp) {
                    $doRepeat = true;

                    while (true === $doRepeat) {
                        $arrRepeat = $arrData;
                        $arrRepeat['startTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['startTime']);
                        $arrRepeat['endTime'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['endTime']);
                        $arrRepeat['mondayTimestampSelectedWeek'] = $dateHelperAdapter->addDaysToTime(7, $arrRepeat['mondayTimestampSelectedWeek']);
                        $arrBookedSlots[] = $arrRepeat;

                        // Stop repeating
                        if ($arrRepeat['mondayTimestampSelectedWeek'] >= $this->bookingRepeatStopWeekTstamp) {
                            $doRepeat = false;
                        }

                        $arrData = $arrRepeat;
                        unset($arrRepeat);
                    }
                }
            }
        }

        if (!empty($arrBookedSlots)) {
            // Sort array by startTime
            usort(
                $arrBookedSlots,
                static function ($a, $b) {
                    return $a['startTime'] <=> $b['startTime'];
                }
            );
        }

        foreach ($arrBookedSlots as $i => $arrData) {
            // Set date
            $arrBookedSlots[$i]['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $arrData['startTime']);
            $arrBookedSlots[$i]['datim'] = sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $arrData['startTime']), $dateAdapter->parse($configAdapter->get('dateFormat'), $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['startTime']), $dateAdapter->parse('H:i', $arrData['endTime']));

            // Set title
            $arrBookedSlots[$i]['title'] = sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->getActiveResource()->title,
                $this->translator->trans('MSC.bookingFor', [], 'contao_default'),
                $this->user->getLoggedInUser()->firstname,
                $this->user->getLoggedInUser()->lastname,
                $dateAdapter->parse($configAdapter->get('datimFormat'), $arrData['startTime']),
                $dateAdapter->parse($configAdapter->get('datimFormat'), $arrData['endTime'])
            );

            // Set booking uuid
            $arrBookedSlots[$i]['bookingUuid'] = $this->getBookingUuid();
            $slot = $this->slotFactory->get(
                SlotBooking::MODE,
                $this->getActiveResource(),
                (int) $arrData['startTime'],
                (int) $arrData['endTime'],
                (int) $arrData['itemsBooked'],
                (int) $arrData['bookingRepeatStopWeekTstamp'],
            );

            // Check if slot is fully booked
            $arrBookedSlots[$i]['isFullyBooked'] = $slot->isFullyBooked();

            // Check if there are enough items available
            $arrBookedSlots[$i]['enoughItemsAvailable'] = $slot->enoughItemsAvailable();

            // Check if booking is possible
            if (!$slot->hasValidDate()) {
                // Invalid time period
                $arrBookedSlots[$i]['isBookable'] = false;
                $arrBookedSlots[$i]['isValidDate'] = false;
                $this->setErrorMessage('RBB.ERR.invalidStartOrEndTime');
            } elseif ($slot->isBookable()) {
                // All ok! Resource is bookable. -> override defaults
                $arrBookedSlots[$i]['isBookable'] = true;
            } elseif (!$slot->isBookable()) {
                // Resource has already been booked by an other user
                $arrBookedSlots[$i]['isBookable'] = false;
                $this->setErrorMessage('RBB.ERR.notEnoughItemsAvailable');
            } else {
                // This case normally should not happen
                $arrBookedSlots[$i]['isBookable'] = false;
                $this->setErrorMessage('RBB.ERR.slotNotBookable');
            }

            if ($slot->isBookedByUser()) {
                $arrBookedSlots[$i]['userHasBooked'] = true;
                $arrBookedSlots[$i]['bookingRelatedToLoggedInUser'] = $slot->getBookingRelatedToLoggedInUser();
            }

            if ($slot->hasBookings()) {
                $arrBookedSlots[$i]['hasBookings'] = true;
                $arrBookedSlots[$i]['bookings'] = $slot->getBookings();
            }
        }

        $bookingCollection = [];

        foreach ($arrBookedSlots as $arrBooking) {
            // Use already available booking entity
            $objBooking = $arrBooking['bookingRelatedToLoggedInUser'];

            if (true !== $arrBooking['userHasBooked'] && null === $objBooking) {
                // Create new booking entity
                $objBooking = new ResourceBookingModel();
            }

            // Add data to the model
            if (null !== $objBooking) {
                foreach ($arrBooking as $k => $v) {
                    if ('id' === $k && empty($v)) {
                        continue;
                    }
                    $objBooking->{$k} = $v;
                }
                $bookingCollection[] = $objBooking;
                // !Do not save the model here, this will be done after
            }
        }

        $this->bookingCollection = new Collection($bookingCollection, 'tl_resource_booking');
    }
}
