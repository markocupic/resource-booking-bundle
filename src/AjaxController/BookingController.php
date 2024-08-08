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
use Contao\Input;
use Contao\Model\Collection;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingProcessException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    private Connection $connection;
    private EventDispatcherInterface $eventDispatcher;
    private SlotFactory $slotFactory;
    private TranslatorInterface $translator;
    private string|null $bookingUuid = null;

    /**
     * Use setter via "#[Required]" attribute injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html.
     */
    #[Required]
    public function _setController(Connection $connection, EventDispatcherInterface $eventDispatcher, SlotFactory $slotFactory, TranslatorInterface $translator): void
    {
        $this->connection = $connection;
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

        // Get the logger
        $logger = $systemAdapter->getContainer()->get('monolog.logger.contao');

        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        $this->connection->beginTransaction();

        // Validate inputs
        // !Important: If you use additional upload fields,
        // it is up to you to perform an input check.
        try {
            $this->validateInputs($this->utils->getAppConfig()['permittedUploadFields']);

            $slotCollection = $this->getSlotCollectionFromRequest();

            // First we check, if booking is possible!
            if (!$this->isBookingPossible($slotCollection)) {
                throw new StopBookingProcessException($this->translator->trans($this->getErrorMessage(), [], 'contao_default'));
            }

            /** @var Collection $objBookings Then we get the booking collection */
            $objBookings = $this->getBookingCollection($slotCollection, $this->utils);

            // Dispatch pre booking event "rbb.event.pre_booking"
            $eventData = new \stdClass();
            $eventData->user = $this->user->getLoggedInUser();
            $eventData->bookingCollection = $objBookings;
            $eventData->ajaxResponse = $ajaxResponse;
            $eventData->sessionBag = $this->sessionBag;

            // Dispatch the pre-booking event.
            $objPreBookingEvent = new PreBookingEvent($eventData);

            $this->eventDispatcher->dispatch($objPreBookingEvent);

            $objBookings?->reset();

            if (null !== $objBookings) {
                while ($objBookings->next()) {
                    $objBooking = $objBookings->current();

                    // Check if mandatory fields are all filled in, see dca mandatory key
                    // Throw a StopBookingProcessException to stop the booking process.
                    if (true !== ($res = $this->utils->checkMandatoryFieldsSet($objBooking->row(), 'tl_resource_booking'))) {
                        $tableName = $res[0];
                        $fieldName = $res[1];
                        $label = $GLOBALS['TL_LANG'][$tableName][$fieldName][0] ?? $fieldName;

                        throw new StopBookingProcessException($this->translator->trans('RBB.ERR.mandatoryFieldNotFilledIn', [$label], 'contao_default'));
                    }

                    // Save booking to the database
                    $objBooking->save();

                    // Log
                    $strLog = sprintf('New resource "%s" (with ID %s) has been booked.', $this->getActiveResource()->title, $objBooking->id);
                    $logger?->log(LogLevel::INFO, $strLog, ['contao' => new ContaoContext(__METHOD__, 'INFO')]);
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
                $this->eventDispatcher->dispatch($objPostBookingEvent);
            }

            if (null !== $objBookings) {
                $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

                // Use event listeners to return a custom message to the user
                if (null === $ajaxResponse->getConfirmationMessage()) {
                    $ajaxResponse->setConfirmationMessage(
                        $this->translator->trans('RBB.MSG.successfullyBookedXItems', [$this->getActiveResource()->title, $objBookings->count()], 'contao_default')
                    );
                }
            } else {
                throw new StopBookingProcessException($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            }

            // Add booking selection to response
            $objBookings->reset();

            $ajaxResponse->setData('bookingSelection', $objBookings->fetchAll());

            $this->connection->commit();
        } catch (StopBookingProcessException $e) {
            $this->connection->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_WARNING);
            $ajaxResponse->setWarningMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.generalBookingError', [], 'contao_default'));
            $logger?->log(LogLevel::ERROR, $e->getMessage(), ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]);
        }
    }

    private function validateInputs(array $permittedUploadFields): void
    {
        $arrKeys = array_keys($_POST);

        foreach ($arrKeys as $key) {
            switch ($key) {
                // Strings
                case 'REQUEST_TOKEN':
                case 'action':
                case 'moduleKey':
                case 'bookingDescription':
                    if (!empty(Input::post($key)) && !\is_string(Input::post($key))) {
                        throw new \Exception($this->translator->trans('RBB.ERR.invalidUploadValueSubmitted', [$key], 'contao_default'));
                    }

                    break;
                // Arrays
                case 'bookingDateSelection':
                    if (!\is_array(Input::post($key))) {
                        throw new \Exception($this->translator->trans('RBB.ERR.invalidUploadValueSubmitted', [$key], 'contao_default'));
                    }

                    break;
                // Integers
                case 'resourceId':
                case 'bookingRepeatStopWeekTstamp':
                    if (empty(Input::post($key)) || (string) (int) Input::post($key) !== Input::post($key)) {
                        throw new \Exception($this->translator->trans('RBB.ERR.invalidUploadValueSubmitted', [$key], 'contao_default'));
                    }

                    break;

                default:
                    // Check if custom field is allowed and registered in the configuration
                    if (!\in_array($key, $permittedUploadFields, true)) {
                        throw new \Exception($this->translator->trans('RBB.ERR.invalidUploadFieldSubmitted', [$key], 'contao_default'));
                    }
            }
        }
    }

    private function getBookingCollection(SlotCollection $slotCollection, Utils $utils): Collection|null
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
                foreach ($slot->dataBooking as $k => $v) {
                    if ('id' === $k && empty($v)) {
                        continue;
                    }

                    $objBooking->{$k} = $v;
                }

                // !Do not save the model here, this will be done later
                $arrAppConfig = $utils->getAppConfig();
                $objBooking->confirmed = $arrAppConfig['autoConfirm'] ? true : false;
                $objBooking->tstamp = time();
                $objBooking->bookingTime = time();

                $bookingCollection[] = $objBooking;
            }
        }

        return !empty($bookingCollection) ? new Collection($bookingCollection, 'tl_resource_booking') : null;
    }
}
