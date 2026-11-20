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

use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekCalendarRenderer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds the HTML of the week view to the data of the week calendar (data.weekCalendarHtml).
 *
 * Runs after the other listeners of the AjaxResponseEvent (lowest priority): the HTML shows
 * the data they have added or changed, e.g. links in the cells of the table.
 */
#[AsEventListener(priority: self::PRIORITY)]
final class RenderWeekCalendarListener
{
    public const PRIORITY = -1024;

    public const DATA_KEY = 'weekCalendarHtml';

    public function __construct(private readonly WeekCalendarRenderer $renderer)
    {
    }

    public function __invoke(AjaxResponseEvent $event): void
    {
        if (RefreshDataController::ACTION !== $event->getAction()) {
            return;
        }

        $response = $event->getAjaxResponse();

        if (AjaxStatus::Success !== $response->getStatus()) {
            return;
        }

        $response->setData(self::DATA_KEY, $this->renderer->render($event->getAppState(), $response->getAllData()));
    }
}
