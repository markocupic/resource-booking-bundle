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
use Markocupic\ResourceBookingBundle\Api\Input\BookingInput;
use Markocupic\ResourceBookingBundle\Api\Output\BookingResult;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Booking\BookingRepository;
use Markocupic\ResourceBookingBundle\Booking\BookingRequestHandler;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Exception\FieldValidationException;
use Markocupic\ResourceBookingBundle\Exception\StopBookingProcessException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAjaxController(self::ACTION)]
final class BookingController extends AbstractController implements ControllerInterface
{
    public const ACTION = 'bookingRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly BookingRequestHandler $bookingRequestHandler,
        private readonly BookingRepository $bookingRepository,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
        parent::__construct($framework, $user);
    }

    /**
     * The booking process:
     *
     *  1. read and validate the input, find the resource
     *  2. lock the resource (transaction), create the slots and check the rules
     *  3. create the bookings, PreBookingEvent (listeners can change them or stop the process)
     *  4. save the bookings, PostBookingEvent (e.g. notifications)
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('default', $this->translator->getLocale());

        try {
            $appState = AppState::fromRequest($request);
            $user = $this->getLoggedInUserOrThrow();
            $input = $this->getBookingInput($request, $appState);
            $resource = $this->bookingRequestHandler->getResource($input, $appState);
            $bookingUuid = Uuid::uuid4()->toString();

            $this->connection->beginTransaction();
            $this->bookingRepository->lockResource((int) $resource->id);

            $slots = $this->bookingRequestHandler->createSlots($input, $resource, $appState, $bookingUuid);

            if (null !== ($errorKey = $this->bookingRequestHandler->getErrorKey($slots))) {
                throw new StopBookingProcessException($this->translator->trans($errorKey, [], 'contao_default'));
            }

            $bookings = $this->bookingRepository->createBookings($slots, $resource);

            $this->eventDispatcher->dispatch(new PreBookingEvent($request, $ajaxResponse, $appState, $user, $bookings));

            if (null !== $bookings) {
                $this->bookingRepository->save($bookings, $resource);
            }

            $bookings = $this->bookingRepository->findByBookingUuid($bookingUuid);

            if (null === $bookings) {
                throw new StopBookingProcessException($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            }

            $this->eventDispatcher->dispatch(new PostBookingEvent($request, $ajaxResponse, $appState, $user, $bookings));

            $this->setSuccessResponse($ajaxResponse, $bookings, $resource);
            $this->connection->commit();
        } catch (StopBookingProcessException $e) {
            $this->rollBack();
            $ajaxResponse->setStatus(AjaxStatus::Warning);
            $ajaxResponse->setResult(new BookingResult(false));

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
            $ajaxResponse->setStatus(AjaxStatus::Error);
            $ajaxResponse->setResult(new BookingResult(false));
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            $this->contaoErrorLogger?->error($e->getMessage());
        }

        return $ajaxResponse;
    }

    private function rollBack(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
    }

    /**
     * @param Collection<ResourceBookingModel> $bookings
     */
    private function setSuccessResponse(AjaxResponse $ajaxResponse, Collection $bookings, ResourceBookingResourceModel $resource): void
    {
        $ajaxResponse->setStatus(AjaxStatus::Success);

        if (null === $ajaxResponse->getConfirmationMessage()) {
            $ajaxResponse->setConfirmationMessage(
                $this->translator->trans('RBB.MSG.successfullyBookedXItems', [$resource->title, $bookings->count()], 'contao_default'),
            );
        }

        // Only the IDs and times of the saved bookings
        $bookings->reset();
        $ajaxResponse->setResult(BookingResult::fromBookingRows($bookings->fetchAll()));
    }

    /**
     * An invalid or not permitted field is shown at the field in the booking window.
     *
     * @throws FieldValidationException
     */
    private function getBookingInput(Request $request, AppState $appState): BookingInput
    {
        try {
            return BookingInput::fromRequest($request, $appState->getPermittedFormFields());
        } catch (InvalidBookingRequestException $e) {
            throw FieldValidationException::forField($e->getField(), $this->translator->trans($e->getTranslationKey(), [$e->getField()], 'contao_default'), $e);
        }
    }
}
