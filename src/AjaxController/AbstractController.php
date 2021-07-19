<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Slot\SlotBooking;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class AbstractController.
 */
abstract class AbstractController
{
    protected ?ResourceBookingResourceModel $activeResource = null;

    protected ?string $bookingUuid = null;

    protected ?ModuleModel $moduleModel = null;

    protected ?Collection $bookingCollection = null;

    protected ContaoFramework $framework;

    protected SessionInterface $session;

    protected RequestStack $requestStack;

    protected SlotFactory $slotFactory;

    protected LoggedInFrontendUser $user;

    protected TranslatorInterface $translator;

    protected SessionBagInterface $sessionBag;

    protected ?string $errorMsg = null;

    /**
     * AbstractController constructor.
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
    protected function initialize(): void
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
        $this->bookingRepeatStopWeekTstamp = (int) $request->request->get('bookingRepeatStopWeekTstamp', null);

        if (null === $this->bookingRepeatStopWeekTstamp) {
            throw new \Exception('No booking repeat stop week timestamp found.');
        }
    }

    /**
     * @throws \Exception
     */
    protected function getActiveResource(): ?ResourceBookingResourceModel
    {
        if (!$this->activeResource) {
            /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
            $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

            $request = $this->requestStack->getCurrentRequest();

            $this->activeResource = $resourceBookingResourceModelAdapter->findPublishedByPk($request->request->get('resourceId'));
        }

        return $this->activeResource;
    }

    /**
     * @throws Exception
     */
    protected function getSlotCollectionFromRequest(): ?SlotCollection
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

        $arrSlotCollection = [];
        $resource = $this->getActiveResource();
        $itemsBooked = (int) $request->request->get('itemsBooked', 1);
        $description = $request->request->has('bookingDescription') ? $stringUtilAdapter->decodeEntities($inputAdapter->post('bookingDescription')) : '';
        $arrDateSelection = $request->request->get('bookingDateSelection', []);
        $this->bookingUuid = $this->getBookingUuid();

        if (!empty($arrDateSelection) && \is_array($arrDateSelection)) {
            foreach ($arrDateSelection as $strTimeSlot) {
                // slotId-startTime-endTime-mondayTimestampSelectedWeek
                $arrTimeSlot = explode('-', $strTimeSlot);

                $timeSlotId = (int) $arrTimeSlot[0];
                $startTime = (int) $arrTimeSlot[1];
                $endTime = (int) $arrTimeSlot[2];

                /** @var SlotMain $slot Create new booking entity */
                $slot = $this->slotFactory->get(
                    SlotBooking::MODE,
                    $resource,
                    $startTime,
                    $endTime,
                    $itemsBooked,
                    (int) $this->bookingRepeatStopWeekTstamp
                );

                $slot->timeSlotId = $timeSlotId;

                $arrSlotCollection[] = $slot;

                // Handle repetitions
                if ($endTime < $this->bookingRepeatStopWeekTstamp) {
                    $doRepeat = true;

                    while (true === $doRepeat) {
                        $startTime = $dateHelperAdapter->addDaysToTime(7, $startTime);
                        $endTime = $dateHelperAdapter->addDaysToTime(7, $endTime);

                        /** @var SlotMain $slot Create new booking entity */
                        $slot = $this->slotFactory->get(
                            SlotBooking::MODE,
                            $resource,
                            $startTime,
                            $endTime,
                            $itemsBooked,
                            (int) $this->bookingRepeatStopWeekTstamp
                        );
                        $slot->timeSlotId = $timeSlotId;

                        $arrSlotCollection[] = $slot;

                        // Stop repeating
                        if ($slot->mondayTimestampSelectedWeek >= $this->bookingRepeatStopWeekTstamp) {
                            $doRepeat = false;
                        }
                    }
                }
            }
        }

        $slotCollection = (new SlotCollection($arrSlotCollection))->sortBy('startTime');

        $controllerAdapter->loadDataContainer('tl_resource_booking');
        $dca = $GLOBALS['TL_DCA']['tl_resource_booking'];

        $arrUserInput = [
            'member' => $this->user->getLoggedInUser()->id,
            'itemsBooked' => $itemsBooked,
            'tstamp' => time(),
            'pid' => $resource->id,
            'description' => $description,
        ];

        // Add data from POST, thus the extension can easily be extended
        foreach (array_keys($_POST) as $k) {
            if (!isset($arrUserInput[$k])) {
                $arrUserInput[$k] = true === $dca['fields'][$k]['eval']['decodeEntities'] ? $stringUtilAdapter->decodeEntities($inputAdapter->post($k)) : $inputAdapter->post($k);
            }
        }

        $slotCollection->reset();

        while ($slotCollection->next()) {
            $slot = $slotCollection->current();
            $arrUserInput['bookingUuid'] = $this->bookingUuid;
            $arrUserInput['timeSlotId'] = $slot->timeSlotId;
            $arrUserInput['startTime'] = $slot->startTime;
            $arrUserInput['endTime'] = $slot->endTime;

            $arrUserInput['title'] = sprintf(
                '%s : %s %s %s [%s - %s]',
                $this->getActiveResource()->title,
                $this->translator->trans('MSC.bookingFor', [], 'contao_default'),
                $this->user->getLoggedInUser()->firstname,
                $this->user->getLoggedInUser()->lastname,
                $dateAdapter->parse($configAdapter->get('datimFormat'), $slot->startTime),
                $dateAdapter->parse($configAdapter->get('datimFormat'), $slot->endTime)
            );
            $slotCollection->newBooking = $arrUserInput;
        }

        return $slotCollection;
    }

    protected function isBookingPossible(?SlotCollection $slotCollection): bool
    {
        if (null === $slotCollection) {
            return false;
        }

        $slotCollection->reset();

        while ($slotCollection->next()) {
            /** @var SlotMain $slot */
            $slot = $slotCollection->current();

            if (!$slot->isBookable()) {
                if (!$slot->hasValidDate()) {
                    // Invalid time period
                    $this->setErrorMessage('RBB.ERR.invalidStartOrEndTime');
                } elseif ($slot->isFullyBooked()) {
                    // Resource has already been booked by an other user
                    $this->setErrorMessage('RBB.ERR.resourceIsAlreadyFullyBooked');
                } elseif (!$slot->isBookable()) {
                    // Resource has already been booked by an other user
                    $this->setErrorMessage('RBB.ERR.notEnoughItemsAvailable');
                } else {
                    // This case normally should not happen
                    $this->setErrorMessage('RBB.ERR.slotNotBookable');
                }

                return false;
            }
        }

        return true;
    }

    protected function getBookingUuid(): string
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

    protected function hasErrorMessage(): bool
    {
        return $this->errorMsg ? true : false;
    }

    protected function getErrorMessage(): ?string
    {
        return $this->errorMsg;
    }

    protected function setErrorMessage(string $error): void
    {
        $this->errorMsg = $error;
    }
}
