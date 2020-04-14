<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\Ajax;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Markocupic\ResourceBookingBundle\Ajax\AjaxHandler;
use Markocupic\ResourceBookingBundle\Session\InitializeSession;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxController
 * @package Markocupic\ResourceBookingBundle\Controller\Ajax
 */
class AjaxController extends AbstractController
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var InitializeSession
     */
    private $initializeSession;

    /**
     * @var AjaxHandler
     */
    private $ajaxHandler;

    /**
     * AjaxController constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param InitializeSession $initializeSession
     * @param AjaxHandler $ajaxHandler
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, InitializeSession $initializeSession, AjaxHandler $ajaxHandler)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->initializeSession = $initializeSession;
        $this->ajaxHandler = $ajaxHandler;
    }

    /**
     * xhttp endpoint
     *
     * @return JsonResponse
     * @throws \Exception
     * @Route("/_resource_booking_controller/ajax", name="resource_booking_ajax_endpoint", defaults={"_scope" = "frontend", "_token_check" = true})
     */
    public function ajaxAction(): JsonResponse
    {
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var  \Symfony\Component\HttpFoundation\Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Handle ajax requests
        if ($environmentAdapter->get('isAjaxRequest') && $request->query->has('sessionId') && $request->request->has('action') && !empty($request->request->get('action')))
        {
            // Initialize application
            $this->initializeSession->initialize();

            // Get action from post request
            $action = $request->request->get('action');

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
            'alertError' => 'This route is reserved to xhttp requests only.',
        ];

        return new JsonResponse($arrReturn);
    }

}

