<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Event;

use Contao\FrontendUser;
use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Contracts\EventDispatcher\Event;

class PostBookingEvent extends Event
{
    public const NAME = 'rbb.event.post_booking';

    private Collection|null $bookingCollection;
    private FrontendUser $user;
    private ArrayAttributeBag $sessionBag;
    private AjaxResponse $ajaxResponse;

    public function __construct(\stdClass $event)
    {
        $this->bookingCollection = $event->bookingCollection;
        $this->ajaxResponse = $event->ajaxResponse;
        $this->user = $event->user;
        $this->sessionBag = $event->sessionBag;
    }

    public function getBookingCollection(): Collection
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
