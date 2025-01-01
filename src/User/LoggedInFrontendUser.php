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

namespace Markocupic\ResourceBookingBundle\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class LoggedInFrontendUser
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function getModel(): MemberModel|null
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $user = $this->getLoggedInUser();

        return $user ? $memberModelAdapter->findByPk($user->id) : null;
    }

    public function getLoggedInUser(): UserInterface|null
    {
        if (null === ($token = $this->tokenStorage->getToken())) {
            return null;
        }

        $user = $token->getUser();

        if ($user instanceof FrontendUser) {
           return $user;
        }

        return null;
    }
}
