<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Model\Collection;
use Contao\System;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    private EventDispatcherInterface $eventDispatcher;
    private SlotFactory $slotFactory;
    private TranslatorInterface $translator;
    private string|null $bookingUuid = null;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(EventDispatcherInterface $eventDispatcher, SlotFactory $slotFactory, TranslatorInterface $translator): void
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->slotFactory = $slotFactory;
        $this->translator = $translator;
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

        // Initialize: get resource from request, etc.
        $this->initialize();

        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        $slotCollection = $this->getSlotCollectionFromRequest();

        // First we check, if booking is possible!
        if (!$this->isBookingPossible($slotCollection)) {
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

        /** @var Collection $objBookings Then we get the booking collection */
        $objBookings = $this->getBookingCollection($slotCollection);

        // Dispatch pre booking event "rbb.event.pre_booking"
        $eventData = new \stdClass();
        $eventData->user = $this->user->getLoggedInUser();
        $eventData->bookingCollection = $objBookings;
        $eventData->ajaxResponse = $ajaxResponse;
        $eventData->sessionBag = $this->sessionBag;

        // Dispatch event
        $objPreBookingEvent = new PreBookingEvent($eventData);
        $this->eventDispatcher->dispatch($objPreBookingEvent, PreBookingEvent::NAME);

        $objBookings?->reset();

        if (null !== $objBookings) {
            while ($objBookings->next()) {
                $objBooking = $objBookings->current();

                // Check if mandatory fields are all filled out, see dca mandatory key
                if (true !== ($success = $this->utils->checkMandatoryFieldsSet($objBooking->row(), 'tl_resource_booking'))) {
                    throw new \Exception('No value detected for the mandatory field '.$success);
                }

                // Save booking to the database
                if (!$objBooking->doNotSave) {
                    $objBooking->save();

                    // Log
                    $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');
                    $strLog = sprintf('New resource "%s" (with ID %s) has been booked.', $this->getActiveResource()->title, $objBooking->id);
                    $logger->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                }
            }
            $ajaxResponse->setData('bookingProcessSucceeded', true);
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
            $this->eventDispatcher->dispatch($objPostBookingEvent, PostBookingEvent::NAME);
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
            $ajaxResponse->getInfoMessage();
        }

        // Add booking selection to response
        $objBookings?->reset();

        $ajaxResponse->setData('bookingSelection', $objBookings ? $objBookings->fetchAll() : []);
    }

    private function getBookingCollection(SlotCollection $slotCollection): Collection|null
    {
        $bookingCollection = [];

        $slotCollection->reset();

        while ($slotCollection->next()) {
            /** @var SlotMain $slot */
            $slot = $slotCollection->current();
            // Use already available booking entity
            $objBooking = $slot->bookingRelatedToLoggedInUser;

            if (true !== $slot->userHasBooked && null === $objBooking) {
                // Create new booking entity
                $objBooking = new ResourceBookingModel();
            }

            // Add data to the model
            if (null !== $objBooking) {
                foreach ($slot->newBooking as $k => $v) {
                    if ('id' === $k && empty($v)) {
                        continue;
                    }
                    $objBooking->{$k} = $v;
                }
                $objBooking->tstamp = time();
                $bookingCollection[] = $objBooking;
                // !Do not save the model here, this will be done after
            }
        }

        return !empty($bookingCollection) ? new Collection($bookingCollection, 'tl_resource_booking') : null;
    }
}
