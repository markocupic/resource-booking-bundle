<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Event;

use Contao\FrontendUser;
use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class PreBookingEvent.
 */
class PreBookingEvent extends Event
{
    const NAME = 'rbb.event.pre_booking';

    /**
     * @var Collection
     */
    private $bookingCollection;

    /**
     * @var FrontendUser
     */
    private $user;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * @var AjaxRequestEvent;
     */
    private $ajaxResponse;

    /**
     * PreBookingEvent constructor.
     */
    public function __construct(\stdClass $event)
    {
        $this->bookingCollection = $event->bookingCollection;
        $this->ajaxResponse = $event->ajaxResponse;
        $this->user = $event->user;
        $this->sessionBag = $event->sessionBag;

    }

    public function getBookingCollection(): ?Collection
    {
        return $this->bookingCollection;
    }

    public function getAjaxResponse(): AjaxResponse
    {
        return $this->ajaxResponse;
    }

    public function getUser(): FrontendUser
    {
        return $this->user;
    }

    public function getSessionBag(): ArrayAttributeBag
    {
        return $this->sessionBag;
    }
}
