<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\MemoryTokenStorage;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Markocupic\ResourceBookingBundle\Ajax\AjaxHandler;
use Markocupic\ResourceBookingBundle\Ajax\AjaxResponse;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Markocupic\ResourceBookingBundle\Csrf\CsrfTokenManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class ResourceBookingWeekcalendarController
 * @package Markocupic\ResourceBookingBundle\Controller\FrontendModule
 */
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    /** @var ContaoFramework */
    private $framework;

    /** @var Initialize */
    private $appInitializer;

    /** @var MemoryTokenStorage */
    private $tokenStorage;

    /** @var CsrfTokenManager */
    private $csrfTokenManager;

    /** @var AjaxHandler */
    private $ajaxHandler;

    /**
     * ResourceBookingWeekcalendarController constructor.
     * @param ContaoFramework $framework
     * @param Initialize $appInitializer
     * @param MemoryTokenStorage $tokenStorage
     * @param CsrfTokenManager $csrfTokenManager
     * @param AjaxHandler $ajaxHandler
     */
    public function __construct(ContaoFramework $framework, Initialize $appInitializer, MemoryTokenStorage $tokenStorage, CsrfTokenManager $csrfTokenManager, AjaxHandler $ajaxHandler)
    {
        $this->framework = $framework;
        $this->appInitializer = $appInitializer;
        $this->tokenStorage = $tokenStorage;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->ajaxHandler = $ajaxHandler;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Is frontend
        if ($page instanceof PageModel && $this->get('contao.routing.scope_matcher')->isFrontendRequest($request))
        {
            /** @var Controller $controllerAdapter */
            $controllerAdapter = $this->framework->getAdapter(Controller::class);

            /** @var System $systemAdapter */
            $systemAdapter = $this->framework->getAdapter(System::class);

            /** @var Environment $environmentAdapter */
            $environmentAdapter = $this->framework->getAdapter(Environment::class);

            $container = $systemAdapter->getContainer();

            if (!$this->csrfTokenManager->hasValidCsrfToken())
            {
                // Generate csrf token, that we will be used as the session bag key
                $container
                    ->get('contao.csrf.token_manager')
                    ->getToken($container->getParameter('contao.csrf_token_name'))
                    ->getValue();

                // redirect
                $controllerAdapter->reload();
            }

            // Initialize application
            $isAjax = $environmentAdapter->get('isAjaxRequest');
            $this->appInitializer->initialize((bool) $isAjax, (int) $model->id, (int) $page->id);

            if ($environmentAdapter->get('isAjaxRequest'))
            {
                $this->getAjaxResponse($request)->send();
                exit;
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Let vue.js do the rest ;-)
        return $template->getResponse();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    protected function getAjaxResponse(Request $request): JsonResponse
    {
        $action = $request->request->get('action', null);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (is_callable([AjaxHandler::class, $action]))
        {
            /** @var AjaxResponse $xhrResponse */
            $xhrResponse = $this->ajaxHandler->{$action}();

            // HOOK: add custom logic
            if (isset($GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse']) && \is_array($GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse']))
            {
                foreach ($GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse'] as $callback)
                {
                    /** @var AjaxResponse $xhrResponse */
                    $systemAdapter->importStatic($callback[0])->{$callback[1]}($action, $xhrResponse, $this);
                }
            }
            return new JsonResponse($xhrResponse->getAll(), 200);
        }

        $xhrResponse = new AjaxResponse();
        $xhrResponse->setStatus(AjaxResponse::STATUS_ERROR);
        $xhrResponse->setErrorMessage(sprintf('Action "%s" not found.', $action));

        return new JsonResponse($xhrResponse->getAll(), 501);
    }

}

