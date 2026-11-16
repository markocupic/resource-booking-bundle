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

namespace Markocupic\ResourceBookingBundle\Event;

use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an Ajax controller of the week calendar has answered the request
 * successfully (no exception), before the response is sent. Listeners can add data
 * to the response or change it, e.g. for an adapted template.
 *
 * Everything in the response is visible to every visitor of the page (developer
 * tools): never add personal data of other members.
 */
class AjaxResponseEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private readonly AjaxResponse $ajaxResponse,
    ) {
    }

    /**
     * The action of the Ajax request, e.g. refreshDataRequest or bookingRequest.
     */
    public function getAction(): string
    {
        return (string) $this->request->request->get('action', '');
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Selection of the week calendar (module, page, resource, week, language).
     */
    public function getAppState(): AppState
    {
        return AppState::fromRequest($this->request);
    }

    public function getAjaxResponse(): AjaxResponse
    {
        return $this->ajaxResponse;
    }
}
