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

use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

class PreBookingEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private readonly AjaxResponse $ajaxResponse,
        private readonly AppState $appState,
        private readonly UserInterface $user,
        private readonly Collection|null $bookingCollection,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getBookingCollection(): Collection|null
    {
        return $this->bookingCollection;
    }

    public function getAjaxResponse(): AjaxResponse
    {
        return $this->ajaxResponse;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    /**
     * State of the week calendar: module, page, resource type, resource, week and language.
     */
    public function getAppState(): AppState
    {
        return $this->appState;
    }
}
