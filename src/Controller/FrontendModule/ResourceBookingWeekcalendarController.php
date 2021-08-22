<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Haste\Util\Url;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleIndex;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\TokenManager;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(type=ResourceBookingWeekcalendarController::TYPE, category="resourceBooking")
 */
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    public const TYPE = 'resourceBookingWeekcalendar';

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private EventDispatcherInterface $eventDispatcher;
    private Initialize $appInitializer;
    private AjaxResponse $ajaxResponse;

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

            /**
             * The module key is necessary to run multiple rbb applications on the same page
             * and is sent as a post parameter on every xhr request.
             *
             * The session data of each rbb instance is stored under $_SESSION[_resource_booking_bundle_attributes][$sessionId.'_'.$userId.'_'.$moduleKey.'_'.$token]
             *
             * The module key (#moduleId_#moduleIndex f.ex. 33_0) contains the module id and the module index
             * The module index is 0, if the current module is the first rbb module on the current page
             * The module index is 1, if the current module is the first rbb module on the current page, etc.
             *
             * Do only run once ModuleIndex::generateModuleIndex() per module instance; */
            $request = $this->requestStack->getCurrentRequest();

            ModuleIndex::generateModuleIndex();
            ModuleKey::setModuleKey($model->id.'_'.ModuleIndex::getModuleIndex());
            $moduleKey = ModuleKey::getModuleKey();

            if (!$environmentAdapter->get('isAjaxRequest') && !$request->query->has('token_'.$moduleKey)) {
                TokenManager::generateToken();
                $url = Url::addQueryString('token_'.$moduleKey.'='.TokenManager::getToken());

                return new RedirectResponse($url);
            }

            TokenManager::setToken($request->query->get('token_'.$moduleKey));

            // Initialize application
            $this->appInitializer->initialize((int) $model->id, (int) $page->id);

            if ($environmentAdapter->get('isAjaxRequest')) {
                $request = $this->requestStack->getCurrentRequest();

                if ($request->request->get('moduleKey') === $moduleKey) {
                    // Send JSON response on xhr requests
                    $this->getAjaxResponse()->send();
                    exit;
                }
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Used, if multiple rbb modules are used on the same page
        $template->moduleKey = ModuleKey::getModuleKey();

        // Let vue.js do the rest ;-)
        return $template->getResponse();
    }

    protected function getAjaxResponse(): JsonResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $data = new \stdClass();
        $data->ajaxResponse = $this->ajaxResponse;
        $data->ajaxResponse->setAction($request->request->get('action'));
        $data->request = $this->requestStack->getCurrentRequest();
        $objAjaxRequestEvent = new AjaxRequestEvent($data);

        // Dispatch "rbb.event.xml_http_request" event
        $this->eventDispatcher->dispatch($objAjaxRequestEvent, AjaxRequestEvent::NAME);

        $response = new JsonResponse();
        $response->setData(
            $this->ajaxResponse
                ->prepareBeforeSend(true)
                ->getAll()
        );

        $response->setStatusCode(200);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }
}
