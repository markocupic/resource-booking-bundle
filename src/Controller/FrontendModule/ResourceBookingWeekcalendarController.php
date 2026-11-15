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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\AppState\ModuleKeyGenerator;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(ResourceBookingWeekcalendarController::TYPE, category: 'resourceBooking', template: 'frontend_module/resource_booking_weekcalendar')]
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    public const TYPE = 'resourceBookingWeekcalendar';

    private const MODULE_KEY_ATTRIBUTE = '_rbb_module_key';

    public function __construct(
        private readonly AjaxResponseFactory $ajaxResponseFactory,
        private readonly AppStateFactory $appStateFactory,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ModuleKeyGenerator $moduleKeyGenerator,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if ($this->scopeMatcher->isFrontendRequest($request) && null !== $page) {
            /*
             * The module key ("<module id>_<index>", e.g. "33_0") identifies the week calendar
             * on the page, several week calendars may be placed on the same page. It is sent
             * with every Ajax request, only the module with this key answers. Nothing is
             * stored in the session: the front end sends the selection (resource type,
             * resource and week) with every request.
             */
            $mainRequest = $this->requestStack->getMainRequest() ?? $request;
            $moduleKey = $this->moduleKeyGenerator->generate($mainRequest, (int) $model->id);
            $request->attributes->set(self::MODULE_KEY_ATTRIBUTE, $moduleKey);

            if ($request->isXmlHttpRequest() && $request->request->has('action') && $request->request->get('moduleKey') === $moduleKey) {
                // Send JSON response on xhr requests
                throw new ResponseException($this->getAjaxResponse($request, $moduleKey, $model, $page));
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getAjaxResponse(Request $request, string $moduleKey, ModuleModel $model, PageModel $page): JsonResponse
    {
        // Validated selection of the front end, attached to the request for the Ajax controllers
        $appState = $this->appStateFactory->create(
            $moduleKey,
            $model,
            $page,
            [
                'resType' => $request->request->get('resType'),
                'res' => $request->request->get('res'),
                'date' => $request->request->get('date'),
            ],
            // First request of the page: select a resource automatically if nothing is selected
            '1' === $request->request->get('initial'),
            $request->getLocale(),
        );

        $appState->attachTo($request);

        $ajaxResponse = $this->ajaxResponseFactory->create($request->request->get('action'));

        $event = new AjaxRequestEvent($request, $ajaxResponse);
        $this->eventDispatcher->dispatch($event);

        $response = new JsonResponse(
            $event->getAjaxResponse()
                ->prepareBeforeSend(true)
                ->getAll(),
        );

        $response->setStatusCode(200);
        // Set the shared max age first: setSharedMaxAge() internally calls
        // setPublic(), so it must run before setPrivate() to keep the response private.
        $response->setSharedMaxAge(0);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Used, if multiple rbb modules are used on the same page
        $template->set('moduleKey', $request->attributes->get(self::MODULE_KEY_ATTRIBUTE, $model->id.'_0'));
        $template->set('csrfToken', $this->contaoCsrfTokenManager->getDefaultTokenValue());

        // The calendar changes with every booking: not indexed by the search
        $template->set('searchable', false);

        // Let vue.js take care of the rest ;-)
        return $template->getResponse();
    }
}
