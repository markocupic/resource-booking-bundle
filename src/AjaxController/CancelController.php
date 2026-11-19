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
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Api\Input\CancelBookingInput;
use Markocupic\ResourceBookingBundle\Api\Output\CancelResult;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Booking\BookingRepository;
use Markocupic\ResourceBookingBundle\Booking\CancellationRules;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\Event\PreCancellingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingCancellationException;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAjaxController(self::ACTION)]
final class CancelController extends AbstractController implements ControllerInterface
{
    public const ACTION = 'cancelBookingRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly BookingRepository $bookingRepository,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
        parent::__construct($framework, $user);
    }

    /**
     * Cancels a booking of the logged-in member and, on request, its future repetitions:
     * DELETE /_rbb/api/bookings/{id}?deleteRepetitions=1 (see WeekCalendarApiController).
     *
     * Listeners of the PreCancellingEvent and the PostCancellingEvent can stop or revert
     * the cancellation by throwing a StopBookingCancellationException.
     *
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('default', $this->translator->getLocale());

        $input = CancelBookingInput::fromRequest($request);

        $this->connection->beginTransaction();

        try {
            $appState = AppState::fromRequest($request);
            $user = $this->user->getLoggedInUser();

            if (null === $user || $input->bookingId <= 0) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            $booking = $this->bookingRepository->findById($input->bookingId);

            if (null === $booking) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.bookingNotFound', [$input->bookingId], 'contao_default'));
            }

            if (!CancellationRules::isOwnedBy($booking->row(), (int) $user->id)) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            if (!CancellationRules::isCancelableByTime($booking->row(), $this->clock->now()->getTimestamp())) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.cancelingPastBookingNotPossible', [], 'contao_default'));
            }

            $bookings = $this->bookingRepository->findBookingsToCancel($booking, (int) $user->id, $input->deleteRepetitions);
            $cancelledBookingIds = [];

            if (null !== $bookings) {
                $this->eventDispatcher->dispatch(new PreCancellingEvent($request, $ajaxResponse, $appState, $user, $bookings));

                $cancelledBookingIds = $this->bookingRepository->delete($bookings);

                $this->eventDispatcher->dispatch(new PostCancellingEvent($request, $ajaxResponse, $appState, $user, $bookings));
            }

            if (!$ajaxResponse->hasConfirmationMessage()) {
                $ajaxResponse->setConfirmationMessage($this->buildConfirmationMessage($input->bookingId, $input->deleteRepetitions, \count($cancelledBookingIds)));
            }

            $ajaxResponse->setStatus(AjaxStatus::Success);
            $ajaxResponse->setResult(new CancelResult(true, $cancelledBookingIds));

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();

            $ajaxResponse->setErrorMessage($e instanceof StopBookingCancellationException ? $e->getMessage() : $this->translator->trans('RBB.ERR.somethingWentWrong', [], 'contao_default'));
            $ajaxResponse->setStatus(AjaxStatus::Error);
            $ajaxResponse->setResult(new CancelResult(false));
            $this->contaoErrorLogger?->error($e->getMessage());
        }

        return $ajaxResponse;
    }

    private function buildConfirmationMessage(int $bookingId, bool $deleteRepetitions, int $count): string
    {
        if ($deleteRepetitions && $count > 1) {
            return $this->translator->trans('RBB.MSG.successfullyCanceledBookingAndItsRepetitions', [$bookingId, (string) ($count - 1)], 'contao_default');
        }

        return $this->translator->trans('RBB.MSG.successfullyCanceledBooking', [$bookingId], 'contao_default');
    }
}
