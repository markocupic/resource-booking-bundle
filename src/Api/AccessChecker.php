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

namespace Markocupic\ResourceBookingBundle\Api;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Checks every request of the week calendar API.
 *
 * The access token proves that Contao has rendered the module for this visitor
 * (page published, page and module visible for the member). As the page may stay
 * open for hours, the following is checked again with every request:
 *
 *  - the logged-in member is the same as when the page was loaded (logout, other member),
 *  - the page is still published (start/stop, preview mode as in Contao),
 *  - a protected page is still accessible for the member (member groups),
 *  - the module still exists and is a week calendar.
 */
class AccessChecker
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly LoggedInFrontendUser $loggedInUser,
        private readonly Security $security,
    ) {
    }

    /**
     * @throws InvalidAccessTokenException if the member has changed (HTTP 401)
     * @throws AccessDeniedHttpException   if the page or the module is no longer accessible (HTTP 403)
     */
    public function check(AccessToken $token): ApiContext
    {
        $this->framework->initialize();

        $user = $this->loggedInUser->getLoggedInUser();
        $memberId = null === $user ? null : (int) $user->id;

        // Logged out, logged in or another member: the page shows the wrong state
        if ($memberId !== $token->memberId) {
            throw new InvalidAccessTokenException('The logged-in member has changed since the page was loaded.');
        }

        $page = $this->framework->getAdapter(PageModel::class)->findPublishedById($token->pageId);

        if (null === $page) {
            throw new AccessDeniedHttpException(\sprintf('Page ID %d is not available.', $token->pageId));
        }

        $page->loadDetails();

        // Same rules as Contao\CoreBundle\EventListener\PageAccessListener
        if ($page->protected) {
            $groups = array_map('intval', (array) $page->groups);

            // -1: guests (not logged in) are allowed
            if (null === $user && !\in_array(-1, $groups, true)) {
                throw new AccessDeniedHttpException(\sprintf('Page ID %d is protected.', $token->pageId));
            }

            if (!$this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $groups)) {
                throw new AccessDeniedHttpException(\sprintf('The member has no access to page ID %d.', $token->pageId));
            }
        }

        $module = $this->framework->getAdapter(ModuleModel::class)->findById($token->moduleId);

        if (null === $module || ResourceBookingWeekcalendarController::TYPE !== $module->type) {
            throw new AccessDeniedHttpException(\sprintf('Module ID %d is not available.', $token->moduleId));
        }

        return new ApiContext($module, $page, $token->moduleKey);
    }
}
