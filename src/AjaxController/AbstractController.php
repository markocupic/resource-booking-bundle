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

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Base class of the Ajax controllers.
 *
 * The controllers are shared services: they must not keep any state between
 * requests (also not in worker mode). Everything a request needs is read from the
 * request and its AppState (AppState::fromRequest()) and passed on as arguments.
 */
abstract class AbstractController
{
    public function __construct(
        protected readonly ContaoFramework $framework,
        protected readonly LoggedInFrontendUser $user,
    ) {
    }

    /**
     * @throws \Exception
     */
    protected function getLoggedInUserOrThrow(): UserInterface
    {
        return $this->user->getLoggedInUser() ?? throw new \Exception('No logged in user found.');
    }
}
