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

use Contao\FrontendUser;
use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

class PreCancellingEvent extends Event
{
    public function __construct(
        private readonly AjaxResponse $ajaxResponse,
        private readonly ArrayAttributeBag $sessionBag,
        private readonly UserInterface $user,
        private readonly Collection|null $bookingCollection,
    ) {
    }

    public function getBookingCollection(): Collection|null
    {
        return $this->bookingCollection;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getSessionBag(): ArrayAttributeBag
    {
        return $this->sessionBag;
    }

    public function getAjaxResponse(): AjaxResponse
    {
        return $this->ajaxResponse;
    }
}
