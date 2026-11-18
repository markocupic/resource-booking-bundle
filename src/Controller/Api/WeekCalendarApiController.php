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

namespace Markocupic\ResourceBookingBundle\Controller\Api;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Markocupic\ResourceBookingBundle\AjaxController\BookingController;
use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\Api\AccessChecker;
use Markocupic\ResourceBookingBundle\Api\AccessTokenManager;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\Api\InvalidAccessTokenException;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The API of the week calendar.
 *
 * Every request carries the access token of the module (header X-Rbb-Access-Token,
 * created when the page was loaded, see ResourceBookingWeekcalendarController). It
 * names the module and the page; the AccessChecker checks them again with every
 * request. Write requests also need the CSRF token of Contao (header X-Csrf-Token).
 *
 * The routes are in the front end scope: the member is logged in by the Contao
 * front end firewall as on a normal page.
 */
#[Route('/_rbb/api', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class WeekCalendarApiController
{
    public const CSRF_HEADER = 'X-Csrf-Token';

    private const BUILT_IN_ACTIONS = [
        RefreshDataController::ACTION,
        BookingFormValidationController::ACTION,
        BookingController::ACTION,
        CancelController::ACTION,
    ];

    public function __construct(
        private readonly AccessTokenManager $accessTokenManager,
        private readonly AccessChecker $accessChecker,
        private readonly AjaxRequestHandler $ajaxRequestHandler,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TranslatorInterface $translator,
        #[Autowire('%contao.csrf_token_name%')]
        private readonly string $csrfTokenName,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Data of the week calendar (first load, periodic refresh, other resource or week).
     * Query: resType, res, date, initial.
     */
    #[Route('/calendar', name: 'markocupic_resource_booking_api_calendar', methods: ['GET'])]
    public function calendar(Request $request): JsonResponse
    {
        return $this->handle($request, RefreshDataController::ACTION);
    }

    /**
     * Checks the selected slots before booking (booking preview).
     */
    #[Route('/bookings/validate', name: 'markocupic_resource_booking_api_validate_booking', methods: ['POST'])]
    public function validateBooking(Request $request): JsonResponse
    {
        return $this->handle($request, BookingFormValidationController::ACTION);
    }

    #[Route('/bookings', name: 'markocupic_resource_booking_api_create_booking', methods: ['POST'])]
    public function createBooking(Request $request): JsonResponse
    {
        return $this->handle($request, BookingController::ACTION);
    }

    /**
     * Query: deleteRepetitions=1 cancels the repetitions too.
     */
    #[Route('/bookings/{id}', name: 'markocupic_resource_booking_api_cancel_booking', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function cancelBooking(Request $request): JsonResponse
    {
        return $this->handle($request, CancelController::ACTION);
    }

    /**
     * Actions of other extensions (#[AsAjaxController]), see docs/EVENTS.md.
     */
    #[Route('/actions/{action}', name: 'markocupic_resource_booking_api_action', requirements: ['action' => '[A-Za-z0-9_]+'], methods: ['POST'])]
    public function action(Request $request, string $action): JsonResponse
    {
        // The actions of the bundle have their own routes (HTTP method, parameters)
        if (\in_array($action, self::BUILT_IN_ACTIONS, true)) {
            return $this->ajaxRequestHandler->createErrorResponse($action, 'RBB.ERR.invalidAjaxAction', Response::HTTP_NOT_FOUND);
        }

        return $this->handle($request, $action);
    }

    private function handle(Request $request, string $action): JsonResponse
    {
        try {
            $context = $this->accessChecker->check($this->accessTokenManager->parse($request->headers->get(AccessTokenManager::HEADER)));

            if (!$request->isMethodSafe() && !$this->isCsrfTokenValid($request)) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
        } catch (InvalidAccessTokenException $e) {
            // The front end asks to reload the page
            return $this->ajaxRequestHandler->createErrorResponse($action, 'RBB.ERR.accessTokenExpired', Response::HTTP_UNAUTHORIZED);
        } catch (AccessDeniedHttpException $e) {
            $this->contaoErrorLogger?->warning(\sprintf('Resource booking: API request "%s" denied: %s', $action, $e->getMessage()));

            return $this->ajaxRequestHandler->createErrorResponse($action, 'RBB.ERR.accessDenied', Response::HTTP_FORBIDDEN);
        }

        $this->initializePageContext($request, $context);

        return $this->ajaxRequestHandler->handle($request, $action, $context);
    }

    private function isCsrfTokenValid(Request $request): bool
    {
        $value = $request->headers->get(self::CSRF_HEADER);

        return \is_string($value) && '' !== $value && $this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $value));
    }

    /**
     * The request does not go through the Contao page controller: provide the page as
     * on the page itself (language, date formats, insert tags).
     */
    private function initializePageContext(Request $request, ApiContext $context): void
    {
        $request->attributes->set('pageModel', $context->page);
        $GLOBALS['objPage'] = $context->page;

        $language = (string) ($context->page->rootLanguage ?: $context->page->language);

        if ('' !== $language) {
            $locale = str_replace('-', '_', $language);
            $request->setLocale($locale);

            if ($this->translator instanceof LocaleAwareInterface) {
                $this->translator->setLocale($locale);
            }
        }
    }
}
