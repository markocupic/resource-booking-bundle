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

namespace Markocupic\ResourceBookingBundle\User;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class LoggedInFrontendUser
{
    public function __construct(
        private readonly Security $security,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function getLoggedInUser(): UserInterface|null
    {
        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $user */
            return $this->security->getUser();
        }

        return null;
    }

    public function getModel(): MemberModel|null
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $user = $this->getLoggedInUser();

        return $user ? $memberModelAdapter->findByPk($user->id) : null;
    }
}
