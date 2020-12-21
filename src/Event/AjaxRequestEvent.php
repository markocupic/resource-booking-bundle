<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Event;

use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class AjaxRequestEvent.
 */
class AjaxRequestEvent extends Event
{
    /**
     * @var AjaxResponse
     */
    private $ajaxResponse;

    public function setAjaxResponse(AjaxResponse $ajaxResponse): void
    {
        $this->ajaxResponse = $ajaxResponse;
    }

    public function getAjaxResponse(): AjaxResponse
    {
        return $this->ajaxResponse;
    }
}
