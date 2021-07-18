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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Exception;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class AbstractController.
 */
abstract class AbstractController
{
    protected ?ResourceBookingResourceModel $activeResource = null;

    protected ?string $bookingUuid = null;

    protected ?ModuleModel $moduleModel = null;

    protected array $arrDateSelection = [];

    protected ?int $bookingRepeatStopWeekTstamp = null;

    protected ?Collection $bookingCollection = null;

    protected ContaoFramework $framework;

    protected SessionInterface $session;

    protected RequestStack $requestStack;

    protected SlotFactory $slotFactory;

    protected LoggedInFrontendUser $user;

    protected TranslatorInterface $translator;

    protected Utils $utils;

    protected Security $security;

    protected EventDispatcherInterface $eventDispatcher;

    protected SessionBagInterface $sessionBag;

    protected ?string $errorMsg = null;

    /**
     * AbstractController constructor.
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RequestStack $requestStack, SlotFactory $slotFactory, LoggedInFrontendUser $user, TranslatorInterface $translator, Utils $utils, Security $security, EventDispatcherInterface $eventDispatcher, string $bagName)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->slotFactory = $slotFactory;
        $this->user = $user;
        $this->translator = $translator;
        $this->utils = $utils;
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @throws Exception
     */
    protected function initialize(): void
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        if (null === $this->user->getLoggedInUser()) {
            throw new Exception('No logged in user found.');
        }

        // Set module model
        $this->moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));

        if (null === $this->moduleModel) {
            throw new Exception('Module model not found.');
        }

        // Get resource
        $request = $this->requestStack->getCurrentRequest();

        if (null === $this->getActiveResource()) {
            throw new Exception(sprintf('Resource with Id %s not found.', $request->request->get('resourceId')));
        }

        // Get booking repeat stop week timestamp
        $this->bookingRepeatStopWeekTstamp = (int) $request->request->get('bookingRepeatStopWeekTstamp', null);

        if (null === $this->bookingRepeatStopWeekTstamp) {
            throw new Exception('No booking repeat stop week timestamp found.');
        }
    }

    /**
     * @throws Exception
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
