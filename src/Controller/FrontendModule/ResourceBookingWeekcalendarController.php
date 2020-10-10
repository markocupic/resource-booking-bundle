<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Markocupic\ResourceBookingBundle\Ajax\AjaxHandler;
use Markocupic\ResourceBookingBundle\Ajax\AjaxResponse;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ResourceBookingWeekcalendarController.
 */
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
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
     * @var Initialize
     */
    private $appInitializer;

    /**
     * @var AjaxHandler
     */
    private $ajaxHandler;

    /**
     * @var string
     */
    private $moduleKey;

    /**
     * ResourceBookingWeekcalendarController constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Initialize $appInitializer, AjaxHandler $ajaxHandler)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->appInitializer = $appInitializer;
        $this->ajaxHandler = $ajaxHandler;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Is frontend
        if ($page instanceof PageModel && $this->get('contao.routing.scope_matcher')->isFrontendRequest($request)) {
            /** @var Environment $environmentAdapter */
            $environmentAdapter = $this->framework->getAdapter(Environment::class);

            /*
             * The module key is necessary to run several rbb applications on the same page
             * and is sent as a post parameter in every xhr request
             *
             * The module key (#moduleId_#moduleIndex f.ex. 33_2) contains the module id and the module index
             * The module index is 1, if the current module is the first rbb module on the current page
             * The module index is 2, if the current module is the first rbb module on the current page, etc.
             *
             */
            if (!isset($GLOBALS['rbb_moduleIndex'])) {
                $GLOBALS['rbb_moduleIndex'] = 1;
            } else {
                ++$GLOBALS['rbb_moduleIndex'];
            }

            $this->moduleKey = $model->id.'_'.$GLOBALS['rbb_moduleIndex'];
            $GLOBALS['rbb_moduleKey'] = $this->moduleKey;

            // Initialize application
            $this->appInitializer->initialize((int) $model->id, (int) $page->id);

            if ($environmentAdapter->get('isAjaxRequest')) {
                $request = $this->requestStack->getCurrentRequest();

                if ($request->request->get('moduleKey') === $this->moduleKey) {
                    $this->getAjaxResponse($request)->send();
                    exit;
                }
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Used, if multiple rbb modules are used on the same page
        $template->moduleKey = $this->moduleKey;

        // Let vue.js do the rest ;-)
        return $template->getResponse();
    }

    /**
     * @throws \Exception
     */
    protected function getAjaxResponse(Request $request): JsonResponse
    {
        $action = $request->request->get('action', null);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (\is_callable([AjaxHandler::class, $action])) {
            /** @var AjaxResponse $xhrResponse */
            $xhrResponse = $this->ajaxHandler->{$action}();

            // HOOK: add custom logic
            if (isset($GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse']) && \is_array($GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse'])) {
                foreach ($GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse'] as $callback) {
                    /** @var AjaxResponse $xhrResponse */
                    $systemAdapter->importStatic($callback[0])->{$callback[1]}($action, $xhrResponse, $this);
                }
            }

            return $this->createJsonResponse($xhrResponse->getAll(), 200);
        }

        $xhrResponse = new AjaxResponse();
        $xhrResponse->setStatus(AjaxResponse::STATUS_ERROR);
        $xhrResponse->setErrorMessage(sprintf('Action "%s" not found.', $action));

        return $this->createJsonResponse($xhrResponse->getAll(), 501);
    }

    protected function createJsonResponse(array $arrData, int $statusCode): JsonResponse
    {
        $response = new JsonResponse();

        $response->setData($arrData);
        $response->setStatusCode($statusCode);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }
}
