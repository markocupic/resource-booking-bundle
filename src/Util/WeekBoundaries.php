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

namespace Markocupic\ResourceBookingBundle\Util;

use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;

/**
 * Computes the first and the last week the user may navigate to. Both depend
 * on the active resource:
 *
 *   - tl_resource_booking_resource.weeksBack
 *   - tl_resource_booking_resource.weeksAhead
 *   - tl_resource_booking_resource.addBookableUntil/bookableUntil
 *
 * Without an active resource the defaults (RbbConfig::RBB_DEFAULT_WEEKS_*) apply.
 * The weeks start on $beginWeek (first day of the week of the timetable, e.g. "monday").
 */
class WeekBoundaries
{
    public function getFirstPermittedWeek(string $beginWeek, ResourceBookingResourceModel|null $resource, int $now): int
    {
        $currentWeek = DateHelper::getFirstDayOfWeek($beginWeek, $now);

        return DateHelper::addWeeks(-ResourceRules::fromModel($resource)->weeksBack, $currentWeek);
    }

    public function getLastPermittedWeek(string $beginWeek, ResourceBookingResourceModel|null $resource, int $now): int
    {
        $rules = ResourceRules::fromModel($resource);
        $currentWeek = DateHelper::getFirstDayOfWeek($beginWeek, $now);
        $lastWeek = DateHelper::addWeeks($rules->weeksAhead, $currentWeek);

        if (null === $rules->bookableUntil) {
            return $lastWeek;
        }

        // The week of the last bookable day, but at least the current week
        $weekOfBookableUntil = DateHelper::getFirstDayOfWeek($beginWeek, $rules->bookableUntil);

        if ($weekOfBookableUntil < $now) {
            return $currentWeek;
        }

        return min($lastWeek, $weekOfBookableUntil);
    }
}
