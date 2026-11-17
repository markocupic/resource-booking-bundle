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
use Markocupic\ResourceBookingBundle\AppState\ModuleKeyGenerator;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(ResourceBookingWeekcalendarController::TYPE, category: 'resourceBooking', template: 'frontend_module/resource_booking_weekcalendar')]
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    public const TYPE = 'resourceBookingWeekcalendar';

    private const MODULE_KEY_ATTRIBUTE = '_rbb_module_key';

    public function __construct(
        private readonly AjaxRequestHandler $ajaxRequestHandler,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly ModuleKeyGenerator $moduleKeyGenerator,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    /**
     * The page model is only needed for the Ajax requests (AppState), the parent
     * method does not expect it: Contao passes it to __invoke() only.
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

            // Ajax request of a week calendar (other Ajax requests on the page are not touched)
            if ($request->isXmlHttpRequest() && $request->request->has('action') && $request->request->has('moduleKey')) {
                if ($request->request->get('moduleKey') !== $moduleKey) {
                    // Ajax request of another week calendar on this page: rendering this
                    // module would be wasted, its HTML is discarded anyway
                    return new Response();
                }

                // Send the JSON response
                throw new ResponseException($this->ajaxRequestHandler->handle($request, $moduleKey, $model, $page));
            }
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Used, if multiple rbb modules are used on the same page
        $template->set('moduleKey', $request->attributes->get(self::MODULE_KEY_ATTRIBUTE, $model->id.'_0'));
        $template->set('csrfToken', $this->contaoCsrfTokenManager->getDefaultTokenValue());

        // The calendar changes with every booking: not indexed by the search
        $template->set('searchable', false);

        // Forced color scheme ("light" or "dark"), empty: automatic (OS preference or data-bs-theme of the page)
        $colorScheme = (string) $model->resourceBooking_colorScheme;
        $template->set('colorScheme', \in_array($colorScheme, ['light', 'dark'], true) ? $colorScheme : '');

        // Let vue.js take care of the rest ;-)
        return $template->getResponse();
    }
}
