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
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
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
        protected readonly Utils $utils,
    ) {
    }

    /**
     * Returns true if the resource belongs to one of the resource types selected
     * in the module (tl_module.resourceBooking_resourceTypes).
     */
    public static function isResourceOfModule(ResourceBookingResourceModel $resource, ModuleModel $moduleModel): bool
    {
        $resourceTypeIds = array_map('intval', StringUtil::deserialize($moduleModel->resourceBooking_resourceTypes, true));

        return \in_array((int) $resource->pid, $resourceTypeIds, true);
    }

    /**
     * @throws \Exception
     */
    protected function getLoggedInUserOrThrow(): UserInterface
    {
        return $this->user->getLoggedInUser() ?? throw new \Exception('No logged in user found.');
    }

    /**
     * The resource of a booking request (POST resourceId): it must be published and
     * belong to a resource type of the module (protection against manipulated requests).
     *
     * @throws \Exception
     */
    protected function getRequestedResource(Request $request, AppState $appState): ResourceBookingResourceModel
    {
        $resource = $this->framework
            ->getAdapter(ResourceBookingResourceModel::class)
            ->findPublishedByPk((int) $request->request->get('resourceId'))
        ;

        if (null === $resource) {
            throw new \Exception(\sprintf('Resource with Id %s not found.', $request->request->get('resourceId')));
        }

        if (!self::isResourceOfModule($resource, $appState->module)) {
            throw new \Exception(\sprintf('Resource with Id %s is not available in module with Id %s.', $resource->id, $appState->getModuleId()));
        }

        return $resource;
    }

    /**
     * @throws \Exception
     */
    protected static function getBookingRepeatStopWeekTstamp(Request $request): int
    {
        if (!$request->request->has('bookingRepeatStopWeekTstamp')) {
            throw new \Exception('No booking repeat stop week timestamp found.');
        }

        return (int) $request->request->get('bookingRepeatStopWeekTstamp');
    }
}
