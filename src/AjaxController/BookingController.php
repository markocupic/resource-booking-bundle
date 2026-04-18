<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\Input;
use Contao\Model\Collection;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingProcessException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    public const REQUEST_NAME = 'bookingRequest';

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private SlotFactory $slotFactory;

    private TranslatorInterface $translator;

    private LoggerInterface|null $contaoGeneralLogger = null;

    private LoggerInterface|null $contaoErrorLogger = null;

    private string|null $bookingUuid = null;

    /**
     * Use setter injectione here.
     */
    #[Required]
    public function _setController(Connection $connection, EventDispatcherInterface $eventDispatcher, SlotFactory $slotFactory, TranslatorInterface $translator, LoggerInterface|null $contaoGeneralLogger = null, LoggerInterface|null $contaoErrorLogger = null): void
    {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->slotFactory = $slotFactory;
        $this->translator = $translator;
        $this->contaoGeneralLogger = $contaoGeneralLogger;
        $this->contaoErrorLogger = $contaoErrorLogger;
    }

    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('default', $this->translator->getLocale());
        $this->initialize();
        $this->connection->beginTransaction();

        try {
            $this->validateInputs($this->utils->getAppConfig()['permittedUploadFields']);

            $slots = $this->getSlotCollectionFromRequest($this->bookingRepeatStopWeekTstamp);

            if (!$this->isBookingPossible($slots)) {
                throw new StopBookingProcessException($this->translator->trans($this->getErrorMessage(), [], 'contao_default'));
            }

            /** @var Collection<ResourceBookingModel> $bookings */
            $bookings = $this->getBookingCollection($slots, $this->utils);

            $this->eventDispatcher->dispatch(
                new PreBookingEvent($request, $ajaxResponse, $this->sessionBag, $this->user->getLoggedInUser(), $bookings),
            );

            $this->saveBookings($bookings);

            /** @var Collection<ResourceBookingModel>|null $bookings */
            $bookings = $this->framework->getAdapter(ResourceBookingModel::class)->findByBookingUuid($this->getBookingUuid());

            if (null === $bookings) {
                throw new StopBookingProcessException($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            }

            $this->eventDispatcher->dispatch(
                new PostBookingEvent($request, $ajaxResponse, $this->sessionBag, $this->user->getLoggedInUser(), $bookings),
            );

            $this->setSuccessResponse($ajaxResponse, $bookings);
            $this->connection->commit();
        } catch (StopBookingProcessException $e) {
            $this->connection->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_WARNING);
            $ajaxResponse->setWarningMessage($e->getMessage());
            $this->contaoErrorLogger?->error($e->getMessage());
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            $this->contaoErrorLogger?->error($e->getMessage());
        }

        return $ajaxResponse;
    }

    /**
     * @param Collection<ResourceBookingModel>|null $bookings
     */
    private function saveBookings(Collection|null $bookings): void
    {
        if (null === $bookings) {
            return;
        }

        $bookings->reset();

        while ($bookings->next()) {
            $booking = $bookings->current();

            if (true !== ($res = $this->utils->checkMandatoryFieldsSet($booking->row(), 'tl_resource_booking'))) {
                [$tableName, $fieldName] = $res;
                $label = $GLOBALS['TL_LANG'][$tableName][$fieldName][0] ?? $fieldName;

                throw new StopBookingProcessException($this->translator->trans('RBB.ERR.mandatoryFieldNotFilledIn', [$label], 'contao_default'));
            }

            $booking->save();
            $this->contaoGeneralLogger?->info(
                \sprintf('New resource "%s" (with ID %s) has been booked.', $this->getActiveResource()->title, $booking->id),
            );
        }
    }

    /**
     * @param Collection<ResourceBookingModel> $bookings
     */
    private function setSuccessResponse(AjaxResponse $ajaxResponse, Collection $bookings): void
    {
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setData('bookingProcessSucceeded', true);

        if (null === $ajaxResponse->getConfirmationMessage()) {
            $ajaxResponse->setConfirmationMessage(
                $this->translator->trans('RBB.MSG.successfullyBookedXItems', [$this->getActiveResource()->title, $bookings->count()], 'contao_default'),
            );
        }

        $bookings->reset();
        $ajaxResponse->setData('bookingSelection', $bookings->fetchAll());
    }

    private function validateInputs(array $permittedFormFields): void
    {
        static $fieldTypes = [
            'string' => ['REQUEST_TOKEN', 'action', 'moduleKey', 'bookingDescription'],
            'array' => ['bookingDateSelection'],
            'boolean' => ['isBlocked'],
            'integer' => ['resourceId', 'bookingRepeatStopWeekTstamp'],
        ];

        foreach (array_keys($_POST) as $key) {
            $value = Input::post($key);

            if (\in_array($key, $fieldTypes['string'], true)) {
                if (!empty($value) && !\is_string($value)) {
                    $this->throwInvalidValue($key);
                }
            } elseif (\in_array($key, $fieldTypes['array'], true)) {
                if (!\is_array($value)) {
                    $this->throwInvalidValue($key);
                }
            } elseif (\in_array($key, $fieldTypes['boolean'], true)) {
                if ('' !== $value && '1' !== $value) {
                    $this->throwInvalidValue($key);
                }
            } elseif (\in_array($key, $fieldTypes['integer'], true)) {
                if (empty($value) || (string) (int) $value !== $value) {
                    $this->throwInvalidValue($key);
                }
            } elseif (!\in_array($key, $permittedFormFields, true)) {
                throw new \Exception($this->translator->trans('RBB.ERR.invalidUploadFieldSubmitted', [$key], 'contao_default'));
            }
        }
    }

    private function throwInvalidValue(string $key): void
    {
        throw new \Exception($this->translator->trans('RBB.ERR.invalidUploadValueSubmitted', [$key], 'contao_default'));
    }

    private function getBookingCollection(SlotCollection $slots, Utils $utils): Collection|null
    {
        $bookings = [];

        $slots->reset();

        while ($slots->next()) {
            /** @var SlotMain $slot */
            $slot = $slots->current();

            // Use already available booking entity
            $arrBooking = $slot->userBooking;

            if (true !== $slot->userHasBooked && null === $arrBooking) {
                // Create a new booking entity
                $booking = new ResourceBookingModel();
            } else {
                // Use the already existing entity instead of creating a new one
                $booking = ResourceBookingModel::findById($arrBooking['id']);
            }

            // Add data to the model
            if (null !== $booking) {
                foreach ($slot->dataBooking as $k => $v) {
                    if ('id' === $k && empty($v)) {
                        continue;
                    }

                    $booking->{$k} = $v;
                }

                // !Do not save the model here, this will be done later
                $appConfig = $utils->getAppConfig();
                $booking->confirmed = (bool) $appConfig['autoConfirm'];
                $booking->tstamp = time();
                $booking->bookingTime = time();

                $bookings[] = $booking;
            }
        }

        return !empty($bookings) ? new Collection($bookings, 'tl_resource_booking') : null;
    }
}
