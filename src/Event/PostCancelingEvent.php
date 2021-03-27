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
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class PostCancelingEvent.
 */
class PostCancelingEvent extends Event
{
    const NAME = 'rbb.event.post_canceling';

    /**
     * @var Collection
     */
    private $cancelingCollection;

    /**
     * @var FrontendUser
     */
    private $user;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * PostCancelingEvent constructor.
     * @param \stdClass $event
     */
    public function __construct(\stdClass $event)
    {
        $this->cancelingCollection = $event->cancelingCollection;
        $this->user = $event->user;
        $this->sessionBag = $event->sessionBag;
    }

    public function getCancelingCollection(): ?Collection
    {
        return $this->cancelingCollection;
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
