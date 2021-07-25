<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Symfony\Component\Security\Core\Security;

class LoggedInFrontendUser
{
    private Security $security;
    private ContaoFramework $framework;

    public function __construct(Security $security, ContaoFramework $framework)
    {
        $this->security = $security;
        $this->framework = $framework;
    }

    public function getLoggedInUser(): ?FrontendUser
    {
        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $user */
            return $this->security->getUser();
        }

        return null;
    }

    public function getModel(): ?MemberModel
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $user = $this->getLoggedInUser();

        return $user ? $memberModelAdapter->findByPk($user->id) : null;
    }
}
