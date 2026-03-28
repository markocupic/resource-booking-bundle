<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleIndex;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\TokenManager;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(ResourceBookingWeekcalendarController::TYPE, category: 'resourceBooking', template: 'mod_resourceBookingWeekcalendar')]
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    public const TYPE = 'resourceBookingWeekcalendar';

    public function __construct(
        private readonly AjaxResponseFactory $ajaxResponseFactory,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Initialize $appInitializer,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        // Is frontend
        if ($this->scopeMatcher->isFrontendRequest($request) && null !== $page) {
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

            if (!$request->isXmlHttpRequest() && !$request->query->has('token_'.$moduleKey)) {
                TokenManager::generateToken();
                $request->query->add(['token_'.$moduleKey => TokenManager::getToken()]);
                $request->overrideGlobals();

                return new RedirectResponse($request->getUri());
            }

            TokenManager::setToken($request->query->get('token_'.$moduleKey));

            // Initialize application
            $this->appInitializer->initialize($model->id, $page->id);

            if ($request->isXmlHttpRequest() && $request->request->has('action') && $request->request->get('moduleKey') === $moduleKey) {
                // Send JSON response on xhr requests
                throw new ResponseException($this->getAjaxResponse($request));
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getAjaxResponse(Request $request): JsonResponse
    {
        $ajaxResponse = $this->ajaxResponseFactory->create($request->request->get('action'));

        // Dispatch "rbb.event.xml_http_request" event
        $event = new AjaxRequestEvent($request, $ajaxResponse);
        $this->eventDispatcher->dispatch($event);

        $response = new JsonResponse(
            $event->getAjaxResponse()
                ->prepareBeforeSend(true)
                ->getAll(),
        );

        $response->setStatusCode(200);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        // Used, if multiple rbb modules are used on the same page
        $template->moduleKey = ModuleKey::getModuleKey();
        $template->csrfToken = $this->contaoCsrfTokenManager->getDefaultTokenValue();

        // Let vue.js take care of the rest ;-)
        return $template->getResponse();
    }
}
