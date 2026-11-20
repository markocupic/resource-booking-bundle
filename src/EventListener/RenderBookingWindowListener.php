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

namespace Markocupic\ResourceBookingBundle\EventListener;

use Markocupic\ResourceBookingBundle\AjaxController\BookingController;
use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelBookingFormController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\WeekCalendar\CalendarDataBuilder;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekCalendarRenderer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adds the HTML of the booking window to the responses of the booking window (data.bookingWindowHtml):
 * opening, preview (every change in the form), booking and cancelling.
 *
 * The window is rendered completely with every response, like a classic form: the
 * values sent are filled in again (variable "form"), the messages and the messages per
 * field are part of the HTML. Runs after the other listeners of the AjaxResponseEvent.
 */
#[AsEventListener(priority: RenderWeekCalendarListener::PRIORITY)]
final class RenderBookingWindowListener
{
    public const DATA_KEY = 'bookingWindowHtml';

    public const BLOCK = 'rbb_booking_window_view';

    public const WINDOW_BOOKING = 'showBookingForm';

    public const WINDOW_CANCEL = 'showCancelBookingForm';

    private const WINDOWS = [
        BookingFormValidationController::ACTION => self::WINDOW_BOOKING,
        BookingController::ACTION => self::WINDOW_BOOKING,
        CancelBookingFormController::ACTION => self::WINDOW_CANCEL,
        CancelController::ACTION => self::WINDOW_CANCEL,
    ];

    public function __construct(
        private readonly WeekCalendarRenderer $renderer,
        private readonly CalendarDataBuilder $calendarDataBuilder,
    ) {
    }

    public function __invoke(AjaxResponseEvent $event): void
    {
        $window = self::WINDOWS[$event->getAction()] ?? null;

        if (null === $window) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getAjaxResponse();
        $appState = $event->getAppState();
        $form = self::getFormValues($request);

        // Data of the active week (time slots for the selection, repetitions); the messages of the
        // week view (e.g. "select a resource") do not belong to the booking window
        $calendar = $this->calendarDataBuilder->build($appState, new AjaxResponse(RefreshDataController::ACTION))->toArray();

        $response->setData(self::DATA_KEY, $this->renderer->renderBlock($appState, self::BLOCK, [
            ...$calendar,
            'bookingWindow' => [
                'action' => $window,
                'response' => $response->getAllData(),
                'booking' => self::getBooking($response, $request),
                'showForm' => self::showForm($window, $event->getAction(), $response),
            ],
            'messages' => $response->toArray()['messages'],
            'fieldErrors' => self::getFieldErrors($appState, $response),
            'form' => $form,
            'selectedSlots' => array_values(array_filter((array) ($form['slots'] ?? []), 'is_string')),
        ]));
    }

    /**
     * The booking form is displayed until the booking succeeded, the cancel form only when
     * the cancel window is opened (afterwards only the result).
     */
    private static function showForm(string $window, string $action, AjaxResponse $response): bool
    {
        if (self::WINDOW_BOOKING === $window) {
            return true !== $response->getData('isBooked');
        }

        return CancelBookingFormController::ACTION === $action && AjaxStatus::Success === $response->getStatus();
    }

    /**
     * Values sent (only strings and lists of strings): filled in again in the form.
     *
     * @return array<string, string|list<string>>
     */
    private static function getFormValues(Request $request): array
    {
        $values = [];

        foreach (Request::METHOD_DELETE === $request->getMethod() ? $request->query->all() : $request->request->all() as $name => $value) {
            if (\is_scalar($value)) {
                $values[(string) $name] = (string) $value;
            } elseif (\is_array($value)) {
                $values[(string) $name] = array_values(array_map('strval', array_filter($value, 'is_scalar')));
            }
        }

        return $values;
    }

    /**
     * Booking of the cancel window: found by the CancelBookingFormController, after cancelling the
     * ID of the request (the booking no longer exists).
     *
     * @return array{id: int, bookingUuid: string}|null
     */
    private static function getBooking(AjaxResponse $response, Request $request): array|null
    {
        $booking = $response->getData('booking');

        if (\is_array($booking)) {
            return ['id' => (int) ($booking['id'] ?? 0), 'bookingUuid' => (string) ($booking['bookingUuid'] ?? '')];
        }

        $id = $request->attributes->get('id');

        if (!\is_scalar($id) || !preg_match('/^\d+$/', (string) $id)) {
            return null;
        }

        $uuid = $request->query->all()['bookingUuid'] ?? '';

        return ['id' => (int) $id, 'bookingUuid' => \is_string($uuid) && preg_match('/^[a-f0-9-]{1,36}$/i', $uuid) ? $uuid : ''];
    }

    /**
     * Messages per field with an id unique on the page: the fields refer to it (aria-describedby).
     *
     * @return list<array{field: string, id: string, message: string}>
     */
    private static function getFieldErrors(AppState $appState, AjaxResponse $response): array
    {
        $fieldErrors = [];

        foreach ($response->getFieldErrors() as $field => $message) {
            $fieldErrors[] = [
                'field' => $field,
                'id' => 'rbbFieldError_'.$appState->moduleKey.'_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $field),
                'message' => $message,
            ];
        }

        return $fieldErrors;
    }
}
