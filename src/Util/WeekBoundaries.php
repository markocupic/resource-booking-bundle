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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * Computes the first and the last week the user may navigate to. Both depend
 * on the active resource:
 *
 *   - tl_resource_booking_resource.weeksBack
 *   - tl_resource_booking_resource.weeksAhead
 *   - tl_resource_booking_resource.addBookableUntil/bookableUntil
 *
 * Without an active resource the defaults (RbbConfig::RBB_DEFAULT_WEEKS_*) apply.
 */
class WeekBoundaries
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    /**
     * @throws \Exception
     */
    public function getFirstPermittedWeek(array $arrAppConfig, ResourceBookingResourceModel|null $resource): int
    {
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        return $dateHelperAdapter->addWeeksToTime(-ResourceBookingRestriction::getWeeksBack($resource), $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig));
    }

    /**
     * @throws \Exception
     */
    public function getLastPermittedWeek(array $arrAppConfig, ResourceBookingResourceModel|null $resource, int $currentTime): int
    {
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $intTstampLastPermittedWeek = $dateHelperAdapter->addWeeksToTime(ResourceBookingRestriction::getWeeksAhead($resource), $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig));

        $bookableUntil = ResourceBookingRestriction::getBookableUntil($resource);

        if (null !== $bookableUntil) {
            $intTstampStop = $dateHelperAdapter->getFirstDayOfWeek($arrAppConfig, $bookableUntil);

            if ($intTstampStop < $intTstampLastPermittedWeek) {
                $intTstampLastPermittedWeek = $intTstampStop;
            }

            if ($intTstampStop < $currentTime) {
                $intTstampLastPermittedWeek = $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig);
            }
        }

        return $intTstampLastPermittedWeek;
    }

    /**
     * Computes the first and the last permitted week and stores them
     * (plus their formatted dates) in the session.
     *
     * @throws \Exception
     */
    public function storePermittedWeeks(SessionBagInterface $sessionBag, array $arrAppConfig, ResourceBookingResourceModel|null $resource, int $currentTime): void
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $intTstampFirstPermittedWeek = $this->getFirstPermittedWeek($arrAppConfig, $resource);
        $sessionBag->set('tstampFirstPermittedWeek', $intTstampFirstPermittedWeek);
        $sessionBag->set('tstampFirstPermittedDate', $dateAdapter->parse('Y-m-d', $intTstampFirstPermittedWeek));

        $intTstampLastPermittedWeek = $this->getLastPermittedWeek($arrAppConfig, $resource, $currentTime);
        $sessionBag->set('tstampLastPermittedWeek', $intTstampLastPermittedWeek);
        $sessionBag->set('tstampLastPermittedWeekDate', $dateAdapter->parse('Y-m-d', $intTstampLastPermittedWeek));
    }
}
