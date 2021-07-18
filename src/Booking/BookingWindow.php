<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Booking;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\Slot\SlotBooking;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BookingWindow.
 */
class BookingWindow
{
    /**
     * @var ResourceBookingResourceModel
     */
    private $activeResource;

    /**
     * @var string
     */
    private $bookingUuid;

    /**
     * @var ModuleModel|null
     */
    private $moduleModel;

    /**
     * @var array
     */
    private $arrDateSelection = [];

    /**
     * @var int
     */
    private $bookingRepeatStopWeekTstamp;

    /**
     * @var Collection
     */
    private $bookingCollection;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SlotFactory
     */
    private $slotFactory;

    /**
     * @var LoggedInFrontendUser
     */
    private $user;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * @var string
     */
    private $errorMsg;

    /**
     * Booking constructor.
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RequestStack $requestStack, SlotFactory $slotFactory, LoggedInFrontendUser $user, TranslatorInterface $translator, string $bagName)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->slotFactory = $slotFactory;
        $this->user = $user;
        $this->translator = $translator;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @throws \Exception
     */
    public function isBookingPossible(): bool
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

    public function getBookingCollection(): Collection
    {
        if (null === $this->bookingCollection) {
            $this->setBookingCollectionFromRequest();
        }

        return $this->bookingCollection;
    }

    /**
     * @throws \Exception
     */
    public function getActiveResource(): ?ResourceBookingResourceModel
    {
        if (!$this->activeResource) {
            /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
            $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

            $request = $this->requestStack->getCurrentRequest();

            $this->activeResource = $resourceBookingResourceModelAdapter->findPublishedByPk($request->request->get('resourceId'));
        }

        return $this->activeResource;
    }

    public function getBookingUuid(): string
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
    public function initialize(): void
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        if (null === $this->user->getLoggedInUser()) {
            throw new \Exception('No logged in user found.');
        }

        // Set module model
        $this->moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));

        if (null === $this->moduleModel) {
            throw new \Exception('Module model not found.');
        }

        // Get resource
        $request = $this->requestStack->getCurrentRequest();

        if (null === $this->getActiveResource()) {
            throw new \Exception(sprintf('Resource with Id %s not found.', $request->request->get('resourceId')));
        }

        // Get booking repeat stop week timestamp
        $this->bookingRepeatStopWeekTstamp = $request->request->get('bookingRepeatStopWeekTstamp', null);

        if (null === $this->bookingRepeatStopWeekTstamp) {
            throw new \Exception('No booking repeat stop week timestamp found.');
        }
    }

    public function hasErrorMessage(): bool
    {
        return $this->errorMsg ? true : false;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMsg;
    }

    private function setErrorMessage(string $error): void
    {
        $this->errorMsg = $error;
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
