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
use Contao\Template;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Initialize
     */
    private $appInitializer;

    /**
     * @var AjaxResponse
     */
    private $ajaxResponse;

    /**
     * @var string
     */
    private $moduleKey;

    /**
     * ResourceBookingWeekcalendarController constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, EventDispatcherInterface $eventDispatcher, Initialize $appInitializer, AjaxResponse $ajaxResponse)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
        $this->appInitializer = $appInitializer;
        $this->ajaxResponse = $ajaxResponse;
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
             * The module key is necessary to run multiple rbb applications on the same page
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
        $objAjaxRequestEvent = new AjaxRequestEvent();
        $objAjaxRequestEvent->setAjaxResponse($this->ajaxResponse);

        // Dispatch Trigger subscribed event listeners
        $this->eventDispatcher->dispatch($objAjaxRequestEvent, 'rbb.event.xml_http_request');

        return $this->createJsonResponse($this->ajaxResponse->getAll(), 200);
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
