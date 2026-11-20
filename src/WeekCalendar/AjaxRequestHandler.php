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

namespace Markocupic\ResourceBookingBundle\WeekCalendar;

use Contao\CoreBundle\Exception\ResponseException;
use Markocupic\ResourceBookingBundle\AjaxController\AjaxControllerRegistry;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\Api\Input\CalendarQuery;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Answers the requests of the week calendar API (see Controller\Api\WeekCalendarApiController):
 * the controller registered for the action (AjaxControllerRegistry, #[AsAjaxController]) builds
 * the response. Afterwards the AjaxResponseEvent lets other extensions add data.
 *
 * The response is always JSON, also in case of an error: the front end can display
 * the error message instead of a generic network error.
 */
class AjaxRequestHandler
{
    public function __construct(
        private readonly AjaxControllerRegistry $ajaxControllers,
        private readonly AjaxResponseFactory $ajaxResponseFactory,
        private readonly AppStateFactory $appStateFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function handle(Request $request, string $action, ApiContext $context): JsonResponse
    {
        if (!$this->ajaxControllers->has($action)) {
            return $this->createErrorResponse($action, 'RBB.ERR.invalidAjaxAction', Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->createJsonResponse($this->process($request, $action, $context)->toArray());
        } catch (ResponseException $e) {
            // Contao responses (e.g. redirects) are passed through
            throw $e;
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(\sprintf('Resource booking: The Ajax request "%s" failed: %s', $action, $e->getMessage()), ['exception' => $e]);

            return $this->createErrorResponse($action, 'RBB.ERR.somethingWentWrong', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Answers the request with the controller of the action, including the AjaxResponseEvent.
     * Also used by the front end module to render the week calendar with the page.
     *
     * @throws \InvalidArgumentException if no controller is registered for the action
     */
    public function process(Request $request, string $action, ApiContext $context): AjaxResponse
    {
        if (!$this->ajaxControllers->has($action)) {
            throw new \InvalidArgumentException(\sprintf('No Ajax controller is registered for the action "%s".', $action));
        }

        $query = CalendarQuery::fromRequest($request);

        // Validated selection of the front end, attached to the request for the Ajax controllers
        $appState = $this->appStateFactory->create(
            $context->moduleKey,
            $context->module,
            $context->page,
            $query->toSelection(),
            $query->initial,
            $request->getLocale(),
        );

        $appState->attachTo($request);

        $ajaxResponse = $this->ajaxControllers
            ->get($action)
            ->generateResponse($request, $this->ajaxResponseFactory->create($action))
        ;

        // Other extensions can add data to the response
        $this->eventDispatcher->dispatch(new AjaxResponseEvent($request, $ajaxResponse));

        return $ajaxResponse;
    }

    /**
     * Error response in the format of the Ajax responses (status "error" and message).
     */
    public function createErrorResponse(string $action, string $translationKey, int $statusCode): JsonResponse
    {
        $ajaxResponse = $this->ajaxResponseFactory->create($action);
        $ajaxResponse->setStatus(AjaxStatus::Error);
        $ajaxResponse->setErrorMessage($this->translator->trans($translationKey, [], 'contao_default'));

        return $this->createJsonResponse($ajaxResponse->toArray(), $statusCode);
    }

    private function createJsonResponse(array $data, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        // The data changes with every booking: never cache it
        $response = new JsonResponse($data, $statusCode);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
