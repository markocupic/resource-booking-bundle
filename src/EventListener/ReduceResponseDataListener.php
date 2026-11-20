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
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The week view and the booking window are rendered on the server: the browser only needs the
 * HTML. Once it has been rendered, the data it was rendered from (calendar, slots, bookings) is
 * removed from the JSON response, apart from a few values for scripts (DATA_KEYS).
 *
 * Runs after the listeners that render the HTML. Listeners with a lower priority can still add
 * data for the browser with AjaxResponse::setData().
 */
#[AsEventListener(priority: self::PRIORITY)]
final class ReduceResponseDataListener
{
    public const PRIORITY = -2048;

    /**
     * Data kept in the JSON response per action: the HTML and some values for scripts.
     */
    public const DATA_KEYS = [
        RefreshDataController::ACTION => [RenderWeekCalendarListener::DATA_KEY, 'activeResourceTypeId', 'activeResourceId', 'activeWeekStart'],
        BookingFormValidationController::ACTION => [RenderBookingWindowListener::DATA_KEY, 'isBookable'],
        BookingController::ACTION => [RenderBookingWindowListener::DATA_KEY, 'isBooked', 'bookings'],
        CancelBookingFormController::ACTION => [RenderBookingWindowListener::DATA_KEY],
        CancelController::ACTION => [RenderBookingWindowListener::DATA_KEY, 'isCancelled', 'cancelledBookingIds'],
    ];

    public function __invoke(AjaxResponseEvent $event): void
    {
        $keys = self::DATA_KEYS[$event->getAction()] ?? null;
        $response = $event->getAjaxResponse();

        // Only if the HTML has been rendered (e.g. not for errors of the week calendar)
        if (null === $keys || null === $response->getData($keys[0])) {
            return;
        }

        $response->retainData($keys);
    }
}
