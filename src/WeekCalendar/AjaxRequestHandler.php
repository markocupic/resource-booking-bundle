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
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
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
            // POST: request body, GET and DELETE: query string
            $input = $request->isMethod('POST') ? $request->request : $request->query;

            // Validated selection of the front end, attached to the request for the Ajax controllers
            $appState = $this->appStateFactory->create(
                $context->moduleKey,
                $context->module,
                $context->page,
                [
                    'resType' => $input->get('resType'),
                    'res' => $input->get('res'),
                    'date' => $input->get('date'),
                ],
                // First request of the page: select a resource automatically if nothing is selected
                '1' === $input->get('initial'),
                $request->getLocale(),
            );

            $appState->attachTo($request);

            $ajaxResponse = $this->ajaxControllers
                ->get($action)
                ->generateResponse($request, $this->ajaxResponseFactory->create($action))
            ;

            // Other extensions can add data to the response
            $this->eventDispatcher->dispatch(new AjaxResponseEvent($request, $ajaxResponse));

            return $this->createJsonResponse($ajaxResponse->prepareBeforeSend(true)->getAll());
        } catch (ResponseException $e) {
            // Contao responses (e.g. redirects) are passed through
            throw $e;
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(\sprintf('Resource booking: The Ajax request "%s" failed: %s', $action, $e->getMessage()), ['exception' => $e]);

            return $this->createErrorResponse($action, 'RBB.ERR.somethingWentWrong', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Error response in the format of the Ajax responses (status "error" and message).
     */
    public function createErrorResponse(string $action, string $translationKey, int $statusCode): JsonResponse
    {
        $ajaxResponse = $this->ajaxResponseFactory->create($action);
        $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        $ajaxResponse->setErrorMessage($this->translator->trans($translationKey, [], 'contao_default'));

        return $this->createJsonResponse($ajaxResponse->getAll(), $statusCode);
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
