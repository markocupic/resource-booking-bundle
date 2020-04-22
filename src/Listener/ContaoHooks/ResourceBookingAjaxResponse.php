<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Listener\ContaoHooks;

use Markocupic\ResourceBookingBundle\Ajax\AjaxResponse;
use Markocupic\ResourceBookingBundle\Controller\Ajax\AjaxController;


/**
 * Class ResourceBookingAjaxResponse
 * @package Markocupic\ResourceBookingBundle\Listener\ContaoHooks
 */
class ResourceBookingAjaxResponse
{
    /**
     * Manipulate the response object
     * ! the xhrResponse is passed by reference
     * @param string $action
     * @param AjaxResponse $xhrResponse
     * @param AjaxController $objController
     */
    public function onBeforeSend(string $action, AjaxResponse &$xhrResponse, AjaxController $objController): void
    {
        if($action === 'fetchDataRequest')
        {
            // Do some stuff
        }

        if($action === 'applyFilterRequest')
        {
            // Do some stuff
        }

        if($action === 'jumpWeekRequest')
        {
            // Do some stuff
        }

        if($action === 'bookingRequest')
        {
            // Do some stuff
        }

        if($action === 'bookingFormValidationRequest')
        {
            // Do some stuff
        }

        if($action === 'cancelBookingRequest')
        {
            // Do some stuff
        }
    }
}
