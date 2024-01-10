<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\PasswordHasher\Exception\LogicException;
use Symfony\Component\Security\Core\Security;

class SlotFactory
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly Utils $utils,
    ) {
    }

    /**
     * @throws \Exception
     */
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

        throw new LogicException(sprintf('Variable $mode should either be "%s" or "%s" "%s" given.', SlotMain::MODE, SlotBooking::MODE, $mode));
    }
}
