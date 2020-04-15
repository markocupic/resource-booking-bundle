<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\Ajax;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\ResourceBookingBundle\Ajax\AjaxHandler;
use Markocupic\ResourceBookingBundle\Session\InitializeSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxController
 * @package Markocupic\ResourceBookingBundle\Controller\Ajax
 */
class AjaxController extends AbstractController
{

    /** @var ContaoFramework */
    private $framework;

    /** @var RequestStack */
    private $requestStack;

    /** @var SessionInterface */
    private $session;

    /** @var string */
    private $bagName;

    /** @var InitializeSession */
    private $initializeSession;

    /** @var AjaxHandler */
    private $ajaxHandler;

    /**
     * AjaxController constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param SessionInterface $session
     * @param string $bagName
     * @param InitializeSession $initializeSession
     * @param AjaxHandler $ajaxHandler
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, SessionInterface $session, string $bagName, InitializeSession $initializeSession, AjaxHandler $ajaxHandler)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->bagName = $bagName;
        $this->initializeSession = $initializeSession;
        $this->ajaxHandler = $ajaxHandler;
    }

    /**
     * xhttp logout route
     *
     * @Route("/_resource_booking/ajax/logout", name="resource_booking_ajax_logout_endpoint", condition="request.isXmlHttpRequest()", defaults={"_scope" = "frontend"})
     */
    public function logoutAction()
    {

        // Unset session
        $sessionBag = $this->session->getBag($this->bagName);
        $sessionBag->clear();

        // Unset cookie
        unset($_COOKIE['PHPSESSID']);
        unset($_COOKIE['_contao_resource_booking_token']);

        // Empty value and expiration one hour before
        setcookie('PHPSESSID', '', time() - 3600);
        setcookie('_contao_resource_booking_token', '', time() - 3600);

        // Logout user
        throw new RedirectResponseException(System::getContainer()->get('security.logout_url_generator')->getLogoutUrl());
    }


    /**
     * xhttp default route
     *
     * @param $action
     * @return JsonResponse
     * @throws \Exception
     * @Route("/_resource_booking/ajax/{action}", name="resource_booking_ajax_default_endpoint", condition="request.isXmlHttpRequest()", defaults={"_scope" = "frontend"})
     */
    public function defaultAjaxAction($action): JsonResponse
    {

        /** @var  \Symfony\Component\HttpFoundation\Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Handle ajax requests
        if ($request->query->has('sessionId'))
        {
            // Initialize application
            $this->initializeSession->initialize(true, null, null);

            if (is_callable([\Markocupic\ResourceBookingBundle\Ajax\AjaxHandler::class, $action]))
            {
                $arrReturn = $this->ajaxHandler->{$action}();
                return new JsonResponse($arrReturn);
            }
            $arrReturn = [
                'status'     => 'error',
                'alertError' => sprintf('Action "%s" not found.', $action),
            ];

            return new JsonResponse($arrReturn);
        }

        $arrReturn = [
            'status'     => 'error',
            'alertError' => 'No session id detected.',
        ];

        return new JsonResponse($arrReturn);
    }

}

