<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Exception;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;

/**
 * Class JumpWeekController.
 */
final class JumpWeekController extends AbstractController implements ControllerInterface
{
    private ApplyFilterController $applyFilterController;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(ApplyFilterController $applyFilterController): void
    {
        $this->applyFilterController = $applyFilterController;
    }

    /**
     * @throws Exception
     */
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $this->applyFilterController->generateResponse($ajaxRequestEvent);
    }
}
