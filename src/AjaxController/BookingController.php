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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model\Collection;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Exception\FieldValidationException;
use Markocupic\ResourceBookingBundle\Exception\StopBookingProcessException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAjaxController(self::ACTION)]
final class BookingController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    public const ACTION = 'bookingRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        Utils $utils,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly SlotFactory $slotFactory,
        private readonly WeekBoundaries $weekBoundaries,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
        parent::__construct($framework, $user, $utils);
    }

    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('default', $this->translator->getLocale());

        try {
            $appState = AppState::fromRequest($request);
            $user = $this->getLoggedInUserOrThrow();
            $resource = $this->getRequestedResource($request, $appState);
            $bookingRepeatStopWeekTstamp = self::getBookingRepeatStopWeekTstamp($request);
            $bookingUuid = Uuid::uuid4()->toString();

            // Serialize all bookings of the same resource: lock the resource record until the
            // transaction is committed. Otherwise two members booking the same slot at the same
            // time could both pass the availability check (double booking). The lock must be
            // acquired before the first read of the transaction (consistent snapshot).
            $this->connection->beginTransaction();
            $this->lockResource((int) $resource->id);

            $this->validateInputs($request, $appState->getPermittedFormFields());

            $slots = $this->getSlotCollectionFromRequest($request, $resource, $appState, $bookingRepeatStopWeekTstamp, $bookingUuid);

            if (null !== ($errorKey = $this->getBookingErrorKey($slots))) {
                throw new StopBookingProcessException($this->translator->trans($errorKey, [], 'contao_default'));
            }

            /** @var Collection<ResourceBookingModel> $bookings */
            $bookings = $this->getBookingCollection($slots, $resource);

            $this->eventDispatcher->dispatch(new PreBookingEvent($request, $ajaxResponse, $appState, $user, $bookings));

            $this->saveBookings($bookings, $resource);

            /** @var Collection<ResourceBookingModel>|null $bookings */
            $bookings = $this->framework->getAdapter(ResourceBookingModel::class)->findByBookingUuid($bookingUuid);

            if (null === $bookings) {
                throw new StopBookingProcessException($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            }

            $this->eventDispatcher->dispatch(new PostBookingEvent($request, $ajaxResponse, $appState, $user, $bookings));

            $this->setSuccessResponse($ajaxResponse, $bookings, $resource);
            $this->connection->commit();
        } catch (StopBookingProcessException $e) {
            $this->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_WARNING);

            if ($e instanceof FieldValidationException) {
                // The messages are listed per field in the booking window (no additional general message)
                foreach ($e->getFieldErrors() as $field => $message) {
                    $ajaxResponse->setFieldError($field, $message);
                }
            } else {
                $ajaxResponse->setWarningMessage($e->getMessage());
            }

            // Expected case (e.g. the slot has just been booked by someone else): no error
            $this->contaoGeneralLogger?->info($e->getMessage());
        } catch (\Exception $e) {
            $this->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            $this->contaoErrorLogger?->error($e->getMessage());
        }

        return $ajaxResponse;
    }

    private function lockResource(int $resourceId): void
    {
        $this->connection->executeQuery('SELECT id FROM tl_resource_booking_resource WHERE id = ? FOR UPDATE', [$resourceId]);
    }

    private function rollBack(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    /**
     * @param Collection<ResourceBookingModel>|null $bookings
     */
    private function saveBookings(Collection|null $bookings, ResourceBookingResourceModel $resource): void
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

                throw FieldValidationException::forField($fieldName, $this->translator->trans('RBB.ERR.mandatoryFieldNotFilledIn', [$label], 'contao_default'));
            }

            $booking->save();
            $this->contaoGeneralLogger?->info(
                \sprintf('New resource "%s" (with ID %s) has been booked.', $resource->title, $booking->id),
            );
        }
    }

    /**
     * @param Collection<ResourceBookingModel> $bookings
     */
    private function setSuccessResponse(AjaxResponse $ajaxResponse, Collection $bookings, ResourceBookingResourceModel $resource): void
    {
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setData('bookingProcessSucceeded', true);

        if (null === $ajaxResponse->getConfirmationMessage()) {
            $ajaxResponse->setConfirmationMessage(
                $this->translator->trans('RBB.MSG.successfullyBookedXItems', [$resource->title, $bookings->count()], 'contao_default'),
            );
        }

        $bookings->reset();
        $ajaxResponse->setData('bookingSelection', $bookings->fetchAll());
    }

    /**
     * @param array<int, string> $permittedFormFields
     *
     * @throws \Exception
     */
    private function validateInputs(Request $request, array $permittedFormFields): void
    {
        try {
            (new BookingRequestValidator())->validate($request->request->all(), $permittedFormFields);
        } catch (InvalidBookingRequestException $e) {
            throw FieldValidationException::forField($e->getField(), $this->translator->trans($e->getTranslationKey(), [$e->getField()], 'contao_default'), $e);
        }
    }

    private function getBookingCollection(SlotCollection $slots, ResourceBookingResourceModel $resource): Collection|null
    {
        $bookings = [];

        foreach ($slots as $slot) {
            // Use already available booking entity
            $arrBooking = $slot->userBooking;

            if (true !== $slot->userHasBooked && null === $arrBooking) {
                // Create a new booking entity
                $booking = new ResourceBookingModel();
            } else {
                // Use the already existing entity instead of creating a new one
                $booking = ResourceBookingModel::findById($arrBooking['id'] ?? null);
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
                $booking->confirmed = ResourceBookingRestriction::isAutoConfirmEnabled($resource);
                $booking->tstamp = time();
                $booking->bookingTime = time();

                $bookings[] = $booking;
            }
        }

        return !empty($bookings) ? new Collection($bookings, 'tl_resource_booking') : null;
    }
}
