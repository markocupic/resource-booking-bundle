<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\Security\Core\Security;

class SlotFactory
{
    private ContaoFramework $framework;
    private Security $security;
    private Utils $utils;

    public function __construct(ContaoFramework $framework, Security $security, Utils $utils)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->utils = $utils;
    }

    public function get(string $mode, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1, int $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        if (SlotMain::MODE === $mode) {
            $slotEntity = new SlotMain($this->framework, $this->security, $this->utils);

            return $slotEntity->create($resource, $startTime, $endTime, $desiredItems, $bookingRepeatStopWeekTstamp);
        }

        if (SlotBooking::MODE === $mode) {
            $slotEntity = new SlotBooking($this->framework, $this->security, $this->utils);

            return $slotEntity->create($resource, $startTime, $endTime, $desiredItems, $bookingRepeatStopWeekTstamp);
        }
    }
}
