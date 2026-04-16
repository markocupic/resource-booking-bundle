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
use Contao\Date;
use Contao\Model\Collection;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\Event\PreCancellingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingCancellationException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CancelController extends AbstractController implements ControllerInterface
{
    public const REQUEST_NAME = 'cancelRequest';

    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    private TranslatorInterface $translator;

    private LoggerInterface|null $contaoErrorLogger = null;

    private LoggerInterface|null $contaoGeneralLogger = null;

    /**
     * Use setter via "#[Required]" attribute injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html.
     */
    #[Required]
    public function _setController(Connection $connection, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator, LoggerInterface|null $contaoErrorLogger = null, LoggerInterface|null $contaoGeneralLogger = null): void
    {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        $this->contaoErrorLogger = $contaoErrorLogger;
        $this->contaoGeneralLogger = $contaoGeneralLogger;
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        // Load language file
        $this->getSystemAdapter()->loadLanguageFile('default', $this->translator->getLocale());

        $request = $this->requestStack->getCurrentRequest();
        $user = $this->user->getLoggedInUser();
        $deleteRepetitions = 'true' === $request->request->get('deleteRepetitions');

        $this->connection->beginTransaction();

        try {
            if (null === $user || !(int) $request->request->get('id') > 0) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            $bookingId = (int) $request->request->get('id');

            if (null === ($objBooking = $this->framework->getAdapter(ResourceBookingModel::class)->findById($bookingId))) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.bookingNotFound', [$bookingId], 'contao_default'));
            }

            if ($objBooking->member !== $user->id) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            $bookingCollection = $this->getBookingsToBeDeleted($objBooking, $user, $deleteRepetitions);

            if (null !== $bookingCollection) {
                // Dispatch pre-cancelling event "rbb.event.pre_cancelling"
                // ! Important
                // Throw a StopBookingCancellationException
                // to interrupt the cancellation process
                $objPreCancellingEvent = new PreCancellingEvent($request, $ajaxResponse, $this->sessionBag, $user, $bookingCollection);
                $this->eventDispatcher->dispatch($objPreCancellingEvent);

                while ($bookingCollection->next()) {
                    $currentBooking = $bookingCollection->current();
                    // Use pre-cancelling subscriber to stop the cancellation process.
                    $intAffected = $currentBooking->delete();

                    if ($intAffected) {
                        // Log
                        $strLog = \sprintf('Resource Booking for "%s" (with ID %s) has been deleted.', $this->getParentResource($currentBooking)->title, $currentBooking->id);

                        $this->contaoGeneralLogger?->info($strLog);
                    }
                }

                // Dispatch post cancelling event "rbb.event.post_cancelling"
                // ! Important
                // Throw a StopBookingCancellationException
                // to revert the cancellation process
                $objPostCancellingEvent = new PostCancellingEvent($request, $ajaxResponse, $this->sessionBag, $user, $bookingCollection);
                $this->eventDispatcher->dispatch($objPostCancellingEvent);
            }

            if (!$ajaxResponse->hasConfirmationMessage()) {
                if ($deleteRepetitions && $bookingCollection->count() > 1) {
                    $ajaxResponse->setConfirmationMessage(
                        $this->translator->trans(
                            'RBB.MSG.successfullyCanceledBookingAndItsRepetitions',
                            [$bookingId, (string) ($bookingCollection->count() - 1)],
                            'contao_default',
                        ),
                    );
                } else {
                    $ajaxResponse->setConfirmationMessage(
                        $this->translator->trans(
                            'RBB.MSG.successfullyCanceledBooking',
                            [$bookingId],
                            'contao_default',
                        ),
                    );
                }
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

    protected function getBookingsToBeDeleted(ResourceBookingModel $objBooking, UserInterface $user, bool $deleteRepetitions): Collection|null
    {
        $bookingUuid = $objBooking->bookingUuid;
        $timeSlotId = $objBooking->timeSlotId;
        $weekday = $this->getDateAdapter()->parse('D', $objBooking->startTime);

        $arrIds = [$objBooking->id];

        // Delete repetitions (bookings with same bookingUuid and same start- and end-time)
        if ($deleteRepetitions) {
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
                $user->id,
            ];

            $objRepetitions = $this->framework
                ->getAdapter(ResourceBookingModel::class)
                ->findBy($arrColumns, $arrValues)
            ;

            if (null !== $objRepetitions) {
                while ($objRepetitions->next()) {
                    if ($this->getDateAdapter()->parse('D', $objRepetitions->startTime) === $weekday) {
                        $arrIds[] = $objRepetitions->id;
                    }
                }
            }
        }

        return $this->framework
            ->getAdapter(ResourceBookingModel::class)
            ->findByIds($arrIds)
        ;
    }

    protected function getParentResource(ResourceBookingModel $objBooking): ResourceBookingResourceModel|null
    {
        $resource = $objBooking->getRelated('pid');

        if (!$resource instanceof ResourceBookingResourceModel) {
            throw new \Exception(\sprintf('Resource for booking with ID %d not found.', $objBooking->id));
        }

        return $resource;
    }

    protected function getDateAdapter(): Adapter
    {
        return $this->framework->getAdapter(Date::class);
    }

    protected function getSystemAdapter(): Adapter
    {
        return $this->framework->getAdapter(System::class);
    }
}
