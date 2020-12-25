<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\User;

use Contao\FrontendUser;
use Symfony\Component\Security\Core\Security;

class LoggedInFrontendUser
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function getLoggedInUser(): ?FrontendUser
    {
        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $user */
            return $this->security->getUser();
        }

        return null;
    }
}
