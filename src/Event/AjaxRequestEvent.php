<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Event;

use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class AjaxRequestEvent extends Event
{
    public const NAME = 'xml_http_request';

    private AjaxResponse $ajaxResponse;
    private Request $request;

    public function __construct(\stdClass $event)
    {
        $this->ajaxResponse = $event->ajaxResponse;
        $this->request = $event->request;
    }

    public function getAjaxResponse(): AjaxResponse
    {
        return $this->ajaxResponse;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
