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
use Contao\Date;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\Event\PreCancellingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingCancellationException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CancelController extends AbstractController implements ControllerInterface
{
    private Connection $connection;
    private TranslatorInterface $translator;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * Use setter via "#[Required]" attribute injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html.
     */
    #[Required]
    public function _setController(Connection $connection, TranslatorInterface $translator, EventDispatcherInterface $eventDispatcher): void
    {
        $this->connection = $connection;
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

        // Get the logger
        $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');

        // Load language file
        $systemAdapter->loadLanguageFile('default', $this->translator->getLocale());

        $request = $this->requestStack->getCurrentRequest();

        $this->connection->beginTransaction();

        try {
            $arrIds = [];

            if (null === $this->user->getLoggedInUser() || !(int) $request->request->get('id') > 0) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

            $id = (int) $request->request->get('id');
            $objBooking = $resourceBookingModelAdapter->findByPk($id);

            if (null === $objBooking) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.bookingNotFound', [$id], 'contao_default'));
            }

            if ((int) $objBooking->member !== (int) $this->user->getLoggedInUser()->id) {
                throw new StopBookingCancellationException($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));
            }

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
                // Dispatch pre cancelling event "rbb.event.pre_cancelling"
                $eventData = new \stdClass();
                $eventData->user = $this->user->getLoggedInUser();
                $eventData->bookingCollection = $objBookingRemove;
                $eventData->sessionBag = $this->sessionBag;
                $eventData->ajaxResponse = $ajaxResponse;

                // Dispatch event
                // ! Important
                // Throw a StopBookingCancellationException
                // to interrupt the cancellation process
                $objPreCancellingEvent = new PreCancellingEvent($eventData);
                $this->eventDispatcher->dispatch($objPreCancellingEvent);

                while ($objBookingRemove->next()) {
                    // Use pre cancelling subscriber to prevent cancelling
                    $intAffected = $objBookingRemove->delete();

                    if ($intAffected) {
                        // Log
                        $strLog = sprintf('Resource Booking for "%s" (with ID %s) has been deleted.', $resourceTitle, $objBookingRemove->id);
                        $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');

                        $logger?->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
                    }
                }

                // Dispatch post cancelling event "rbb.event.post_cancelling"
                $eventData = new \stdClass();
                $eventData->user = $this->user->getLoggedInUser();
                $eventData->bookingCollection = $objBookingRemove;
                $eventData->sessionBag = $this->sessionBag;
                $eventData->ajaxResponse = $ajaxResponse;

                // Dispatch event
                // ! Important
                // Throw a StopBookingCancellationException
                // to revert the cancellation process
                $objPostCancellingEvent = new PostCancellingEvent($eventData);
                $this->eventDispatcher->dispatch($objPostCancellingEvent);
            }

            if (!$ajaxResponse->hasConfirmationMessage()) {
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

            $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', true);

            $this->connection->commit();
        } catch (StopBookingCancellationException $e) {
            $this->connection->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', false);
            $ajaxResponse->setErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setData('cancelBookingProcessSucceeded', false);
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.somethingWentWrong', [], 'contao_default'));

            // Add a system log entry.
            $logger?->log(LogLevel::ERROR, $e->getMessage(), ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
        }
    }
}
