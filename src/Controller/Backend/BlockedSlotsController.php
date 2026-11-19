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

namespace Markocupic\ResourceBookingBundle\Controller\Backend;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\System;
use Markocupic\ResourceBookingBundle\Backend\BlockedSlotCalendar;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Week view of a resource for the back end widget "rbbBlockedSlots" (blocked
 * periods in the mode "slots"). Query parameters: resource, week (any time
 * within the week, default: now) and period (the blocked period being edited).
 */
#[Route('%contao.backend.route_prefix%/rbb/blocked-slots', name: self::class, defaults: ['_scope' => 'backend'], methods: ['GET'])]
class BlockedSlotsController extends AbstractController
{
    public function __construct(
        private readonly BlockedSlotCalendar $calendar,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'blockedPeriod');

        $this->initializeContaoFramework();

        // Weekday names (Date::parse('D'))
        $this->getContaoAdapter(System::class)->loadLanguageFile('default');

        $resource = $this->getContaoAdapter(ResourceBookingResourceModel::class)->findById($request->query->getInt('resource'));

        if (null === $resource) {
            throw new NotFoundHttpException('Resource not found.');
        }

        $week = $request->query->getInt('week') ?: $this->clock->now()->getTimestamp();

        $response = new JsonResponse($this->calendar->getWeek($resource, $week, $request->query->getInt('period')));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
