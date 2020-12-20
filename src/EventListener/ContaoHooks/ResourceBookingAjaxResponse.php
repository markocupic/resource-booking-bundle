<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Markocupic\ResourceBookingBundle\Ajax\AjaxResponse;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/**
 * Class ResourceBookingAjaxResponse.
 */
class ResourceBookingAjaxResponse
{
    public function onBeforeSend(string $action, AjaxResponse &$xhrResponse, ResourceBookingWeekcalendarController $objController): void
    {
        if ('fetchDataRequest' === $action) {
            // Do some stuff
        }

        if ('applyFilterRequest' === $action) {
            // Do some stuff
        }

        if ('jumpWeekRequest' === $action) {
            // Do some stuff
        }

        if ('bookingRequest' === $action) {
            // Do some stuff
        }

        if ('bookingFormValidationRequest' === $action) {
            // Do some stuff
        }

        if ('cancelBookingRequest' === $action) {
            // Do some stuff
        }
    }
}
