<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventSubscriber;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\AjaxController\BookingController;
use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\Booking\BookingMain;
use Markocupic\ResourceBookingBundle\Booking\BookingWindow;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class AjaxRequestEventSubscriber.
 */
final class AjaxRequestEventSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 1000;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var BookingMain
     */
    private $bookingMain;

    /**
     * @var BookingWindow
     */
    private $bookingWindow;

    /**
     * @var LoggedInFrontendUser
     */
    private $user;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    private $services = [];

    private $resources = [];

    /**
     * AjaxRequestEventSubscriber constructor.
     */
    public function __construct(ContaoFramework $framework, BookingMain $bookingMain, BookingWindow $bookingWindow, LoggedInFrontendUser $user, SessionInterface $session, RequestStack $requestStack, TranslatorInterface $translator, Utils $utils, string $bagName, Security $security, EventDispatcherInterface $eventDispatcher)
    {
        $this->framework = $framework;
        $this->bookingMain = $bookingMain;
        $this->bookingWindow = $bookingWindow;
        $this->user = $user;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->utils = $utils;
        $this->sessionBag = $session->getBag($bagName);
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function add(ControllerInterface $resource, string $alias, string $id): void
    {
        $this->resources[$alias] = $resource;
        $this->services[$alias] = $id;
    }

    /**
     * Get a resource by alias.
     *
     * @param $alias
     *
     * @return mixed
     */
    public function get($alias)
    {
        if (\array_key_exists($alias, $this->resources)) {
            return $this->resources[$alias];
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AjaxRequestEvent::NAME => ['onXmlHttpRequest', self::PRIORITY],
        ];
    }

    public function onXmlHttpRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isXmlHttpRequest()) {
            $action = $request->request->get('action', null);
            $alias = str_replace('Request', '', $action);

            if ('booking' === $alias || 'cancelBooking' === $alias || 'applyFilter' === $alias || 'jumpWeek' === $alias || 'bookingFormValidation' === $alias) {
                if (\array_key_exists($alias, $this->resources)) {
                    /** @var BookingController $controller */
                    $controller = $this->get($alias);
                    $controller->generateResponse($ajaxRequestEvent);
                }
            } elseif (null !== $action) {
                if (\is_callable([self::class, 'on'.ucfirst($action)])) {
                    $this->{'on'.ucfirst($action)}($ajaxRequestEvent);
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function onFetchDataRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->bookingMain->fetchData());
    }
}
