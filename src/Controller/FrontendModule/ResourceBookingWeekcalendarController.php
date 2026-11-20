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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\Api\AccessTokenManager;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\AppState\ModuleKeyGenerator;
use Markocupic\ResourceBookingBundle\EventListener\RenderWeekCalendarListener;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsFrontendModule(ResourceBookingWeekcalendarController::TYPE, category: 'resourceBooking', template: 'frontend_module/resource_booking_weekcalendar')]
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    public const TYPE = 'resourceBookingWeekcalendar';

    private const PAGE_ATTRIBUTE = '_rbb_page';

    public function __construct(
        private readonly AccessTokenManager $accessTokenManager,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly LoggedInFrontendUser $loggedInUser,
        private readonly ModuleKeyGenerator $moduleKeyGenerator,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AjaxRequestHandler $ajaxRequestHandler,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Contao passes the page model to __invoke() only: keep it for getResponse().
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $request->attributes->set(self::PAGE_ATTRIBUTE, $page);

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        /*
         * The module key ("<module id>_<index>", e.g. "33_0") identifies the week calendar
         * on the page, several week calendars may be placed on the same page.
         */
        $mainRequest = $this->requestStack->getMainRequest() ?? $request;
        $moduleKey = $this->moduleKeyGenerator->generate($mainRequest, (int) $model->id);

        $template->set('moduleKey', $moduleKey);
        $template->set('csrfToken', $this->contaoCsrfTokenManager->getDefaultTokenValue());

        /*
         * The API (WeekCalendarApiController) answers the requests of the week calendar.
         * Contao has just checked that the visitor may see this page and this module:
         * the access token proves it to the API. It is created anew with every page load.
         */
        $page = $request->attributes->get(self::PAGE_ATTRIBUTE);
        $user = $this->loggedInUser->getLoggedInUser();

        $template->set('accessToken', !$page instanceof PageModel ? '' : $this->accessTokenManager->create((int) $model->id, (int) $page->id, $moduleKey, null === $user ? null : (int) $user->id));

        /*
         * The week calendar is rendered with the page (selection of the query string, e.g. a link
         * "?resType=1&res=2&date=2026-11-23"): no loading indicator, readable without JavaScript.
         * The front end replaces it with every request of the week calendar.
         */
        $weekCalendar = $page instanceof PageModel ? $this->renderWeekCalendar($mainRequest, $request, $model, $page, $moduleKey) : null;

        $template->set('weekCalendarHtml', $weekCalendar['html'] ?? '');
        $template->set('weekCalendarMessages', $weekCalendar['messages'] ?? []);
        $template->set('apiUrls', [
            'calendar' => $this->urlGenerator->generate('markocupic_resource_booking_api_calendar'),
            'validateBooking' => $this->urlGenerator->generate('markocupic_resource_booking_api_validate_booking'),
            // POST: create bookings, DELETE {bookings}/{id}: cancel a booking
            'bookings' => $this->urlGenerator->generate('markocupic_resource_booking_api_create_booking'),
            // Actions of other extensions: the front end replaces "__action__"
            'action' => $this->urlGenerator->generate('markocupic_resource_booking_api_action', ['action' => '__action__']),
        ]);

        // The calendar changes with every booking: not indexed by the search
        $template->set('searchable', false);

        // Forced color scheme ("light" or "dark"), empty: automatic (OS preference or data-bs-theme of the page)
        $colorScheme = (string) $model->resourceBooking_colorScheme;
        $template->set('colorScheme', \in_array($colorScheme, ['light', 'dark'], true) ? $colorScheme : '');

        $response = $template->getResponse();

        // The page contains the access token of this visitor: it must never be stored in
        // a shared cache (the setting is merged into the response of the page)
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * Answers an internal request of the week calendar (refreshDataRequest, same as the API, incl.
     * the AjaxResponseEvent). Errors are logged: the front end loads the week calendar then.
     *
     * @return array{html: string, messages: array<string, string|null>}|null
     */
    private function renderWeekCalendar(Request $mainRequest, Request $request, ModuleModel $model, PageModel $page, string $moduleKey): array|null
    {
        $parameters = ['initial' => '1'];

        foreach (['resType', 'res', 'date'] as $name) {
            $value = $mainRequest->query->all()[$name] ?? null;

            if (\is_scalar($value)) {
                $parameters[$name] = (string) $value;
            }
        }

        // Only the selection: other parameters of the page are not passed on
        $calendarRequest = Request::create($mainRequest->getSchemeAndHttpHost().$mainRequest->getBaseUrl().$mainRequest->getPathInfo(), Request::METHOD_GET, $parameters);
        $calendarRequest->setLocale($request->getLocale());
        $calendarRequest->attributes->set('_scope', 'frontend');
        $calendarRequest->attributes->set('pageModel', $page);

        try {
            $response = $this->ajaxRequestHandler->process($calendarRequest, RefreshDataController::ACTION, new ApiContext($model, $page, $moduleKey));
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(\sprintf('Resource booking: The week calendar of module %d could not be rendered: %s', (int) $model->id, $e->getMessage()), ['exception' => $e]);

            return null;
        }

        $html = $response->getData(RenderWeekCalendarListener::DATA_KEY);

        return \is_string($html) ? ['html' => $html, 'messages' => $response->toArray()['messages']] : null;
    }
}
