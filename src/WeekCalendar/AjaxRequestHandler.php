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
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Exception\UnknownAjaxActionException;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Answers the Ajax requests of the week calendar (see ResourceBookingWeekcalendarController).
 *
 * The response is always JSON, also in case of an error: the front end can display
 * the error message instead of a generic network error.
 */
class AjaxRequestHandler
{
    public function __construct(
        private readonly AjaxResponseFactory $ajaxResponseFactory,
        private readonly AppStateFactory $appStateFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function handle(Request $request, string $moduleKey, ModuleModel $model, PageModel $page): JsonResponse
    {
        $action = (string) $request->request->get('action', '');

        try {
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

            $event = new AjaxRequestEvent($request, $this->ajaxResponseFactory->create($action));
            $this->eventDispatcher->dispatch($event);

            return $this->createJsonResponse($event->getAjaxResponse()->prepareBeforeSend(true)->getAll());
        } catch (ResponseException $e) {
            // Contao responses (e.g. redirects) are passed through
            throw $e;
        } catch (UnknownAjaxActionException) {
            return $this->createErrorResponse($action, 'RBB.ERR.invalidAjaxAction', Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(\sprintf('Resource booking: The Ajax request "%s" failed: %s', $action, $e->getMessage()), ['exception' => $e]);

            return $this->createErrorResponse($action, 'RBB.ERR.somethingWentWrong', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function createErrorResponse(string $action, string $translationKey, int $statusCode): JsonResponse
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
