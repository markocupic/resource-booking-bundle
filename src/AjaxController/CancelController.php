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

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Model\Collection;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\Event\PreCancellingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingCancellationException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAjaxController(self::ACTION)]
final class CancelController extends AbstractController implements ControllerInterface
{
    public const ACTION = 'cancelBookingRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        Utils $utils,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        parent::__construct($framework, $user, $utils);
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->getSystemAdapter()->loadLanguageFile('default', $this->translator->getLocale());

        // DELETE /_rbb/api/bookings/{id}?deleteRepetitions=1 (see WeekCalendarApiController)
        $deleteRepetitions = \in_array((string) $request->query->get('deleteRepetitions'), ['1', 'true'], true);

        $this->connection->beginTransaction();

        try {
            $appState = AppState::fromRequest($request);
            $user = $this->user->getLoggedInUser();
            $bookingId = (int) $request->attributes->get('id');

            if (null === $user || $bookingId <= 0) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            $booking = $this->framework->getAdapter(ResourceBookingModel::class)->findById($bookingId);

            if (null === $booking) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.bookingNotFound', [$bookingId], 'contao_default'));
            }

            if ((int) $booking->member !== (int) $user->id) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            // Bookings in the past cannot be canceled (the front end hides the button,
            // but the server must not rely on that).
            if (!self::isCancelableByTime($booking->row(), $this->getCurrentTime())) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.cancelingPastBookingNotPossible', [], 'contao_default'));
            }

            $bookings = $this->getBookingsToBeDeleted($booking, $user, $deleteRepetitions);

            if (null !== $bookings) {
                // Dispatch pre-cancelling event "rbb.event.pre_cancelling"
                // !Important
                // Throw a StopBookingCancellationException
                // to interrupt the cancellation process
                $this->eventDispatcher->dispatch(new PreCancellingEvent($request, $ajaxResponse, $appState, $user, $bookings));

                $this->deleteBookings($bookings);

                // Dispatch post cancelling event "rbb.event.post_cancelling"
                // !Important
                // Throw a StopBookingCancellationException
                // to revert the cancellation process
                $this->eventDispatcher->dispatch(new PostCancellingEvent($request, $ajaxResponse, $appState, $user, $bookings));
            }

            if (!$ajaxResponse->hasConfirmationMessage()) {
                $ajaxResponse->setConfirmationMessage($this->buildConfirmationMessage($bookingId, $deleteRepetitions, $bookings));
            }

            $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', true);

            $this->connection->commit();
        } catch (\Exception $e) {
            match (true) {
                $e instanceof StopBookingCancellationException => $ajaxResponse->setErrorMessage($e->getMessage()),
                default => $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.somethingWentWrong', [], 'contao_default')),
            };

            $this->connection->rollBack();

            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', false);
            $this->contaoErrorLogger?->error($e->getMessage());
        }

        return $ajaxResponse;
    }

    /**
     * A booking can be canceled until its end (same rule as Slot::isInBookingWindow()).
     *
     * @param array<string, mixed> $booking
     */
    public static function isCancelableByTime(array $booking, int $currentTime): bool
    {
        return (int) ($booking['endTime'] ?? 0) >= $currentTime;
    }

    protected function getCurrentTime(): int
    {
        return time();
    }

    private function deleteBookings(Collection $bookings): void
    {
        while ($bookings->next()) {
            $booking = $bookings->current();

            if ($booking->delete()) {
                $this->contaoGeneralLogger?->info(\sprintf(
                    'Resource Booking for "%s" (with ID %s) has been deleted.',
                    $this->getParentResource($booking)->title,
                    $booking->id,
                ));
            }
        }
    }

    private function buildConfirmationMessage(int $bookingId, bool $deleteRepetitions, Collection|null $bookings): string
    {
        if ($deleteRepetitions && null !== $bookings && $bookings->count() > 1) {
            return $this->translator->trans(
                'RBB.MSG.successfullyCanceledBookingAndItsRepetitions',
                [$bookingId, (string) ($bookings->count() - 1)],
                'contao_default',
            );
        }

        return $this->translator->trans('RBB.MSG.successfullyCanceledBooking', [$bookingId], 'contao_default');
    }

    private function getBookingsToBeDeleted(ResourceBookingModel $booking, UserInterface $user, bool $deleteRepetitions): Collection|null
    {
        $arrIds = [$booking->id];

        // Collect repetitions (bookings with same bookingUuid, timeSlot, and weekday)
        if ($deleteRepetitions) {
            $weekday = $this->getDateAdapter()->parse('D', $booking->startTime);

            $bookings = $this->framework
                ->getAdapter(ResourceBookingModel::class)
                ->findBy(
                    [
                        'tl_resource_booking.bookingUuid=?',
                        'tl_resource_booking.timeSlotId=?',
                        'tl_resource_booking.id!=?',
                        'tl_resource_booking.member=?',
                        // Past repetitions of the series are kept
                        'tl_resource_booking.endTime>=?',
                    ],
                    [
                        $booking->bookingUuid,
                        $booking->timeSlotId,
                        $booking->id,
                        $user->id,
                        $this->getCurrentTime(),
                    ],
                )
            ;

            if (null !== $bookings) {
                while ($bookings->next()) {
                    if ($this->getDateAdapter()->parse('D', $bookings->startTime) === $weekday) {
                        $arrIds[] = $bookings->id;
                    }
                }
            }
        }

        return $this->framework
            ->getAdapter(ResourceBookingModel::class)
            ->findByIds($arrIds)
        ;
    }

    private function getParentResource(ResourceBookingModel $booking): ResourceBookingResourceModel
    {
        $resource = $booking->getRelated('pid');

        if (!$resource instanceof ResourceBookingResourceModel) {
            throw new \RuntimeException(\sprintf('Resource for booking with ID %d not found.', $booking->id));
        }

        return $resource;
    }

    private function getDateAdapter(): Adapter
    {
        return $this->framework->getAdapter(Date::class);
    }

    private function getSystemAdapter(): Adapter
    {
        return $this->framework->getAdapter(System::class);
    }
}
