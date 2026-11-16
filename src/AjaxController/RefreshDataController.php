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

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\WeekCalendar\CalendarDataBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Periodic refresh of the week calendar (every 15 seconds and after bookings).
 */
#[AsAjaxController(self::ACTION)]
final class RefreshDataController implements ControllerInterface
{
    public const ACTION = 'refreshDataRequest';

    public function __construct(private readonly CalendarDataBuilder $calendarDataBuilder)
    {
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->calendarDataBuilder->build(AppState::fromRequest($request), $ajaxResponse));

        return $ajaxResponse;
    }
}
