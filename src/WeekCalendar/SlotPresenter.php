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

namespace Markocupic\ResourceBookingBundle\WeekCalendar;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Markocupic\ResourceBookingBundle\Slot\Slot;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;

/**
 * Converts a slot into the data that is sent to the front end (week calendar,
 * booking preview): the results of the booking rules and the texts for the
 * display. Only the listed keys are sent, no rows of the database.
 */
class SlotPresenter
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Slot $slot): array
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $startTimeString = UtcTimeHelper::parseStartTime($slot->startTime);
        $endTimeString = UtcTimeHelper::parseEndTime($slot->endTime);
        $dateString = $dateAdapter->parse((string) $this->framework->getAdapter(Config::class)->get('dateFormat'), $slot->startTime);
        $memberBooking = $slot->getMemberBooking();

        return [
            'timeSlotId' => $slot->timeSlotId,
            'resourceId' => $slot->getResourceId(),
            'startTime' => $slot->startTime,
            'endTime' => $slot->endTime,

            // Results of the booking rules
            'isBookable' => $slot->isBookable(),
            'isValidTimeSlot' => $slot->isValidTimeSlot,
            'hasValidItemCount' => $slot->hasValidItemCount(),
            'isInBookingWindow' => $slot->isInBookingWindow(),
            'isAvailableOnWeekday' => $slot->isAvailableOnWeekday(),
            'isInBlockedPeriod' => $slot->isInBlockedPeriod(),
            'blockedPeriodDescription' => $slot->blockedPeriodDescription,
            'canFulfillRequestedItems' => $slot->canFulfillRequestedItems(),
            'remainingItems' => $slot->getRemainingItems(),
            'isFullyBooked' => $slot->isFullyBooked(),
            'hasBookings' => $slot->hasBookings(),
            'isMemberLoggedIn' => $slot->isMemberLoggedIn(),
            // Only the IDs of the own booking (cancellation)
            'memberBooking' => null === $memberBooking ? null : ['id' => (int) $memberBooking['id'], 'bookingUuid' => (string) ($memberBooking['bookingUuid'] ?? '')],
            'isCancelable' => $slot->isCancelable(),

            // Texts for the display
            'startTimeString' => $startTimeString,
            'endTimeString' => $endTimeString,
            'date' => $dateString,
            // E.g. "Mo, 16.11.2026: 08:00 - 09:00"
            'datimSpanString' => \sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $slot->startTime), $dateString, $startTimeString, $endTimeString),
            'timeSpanString' => $startTimeString.' - '.$endTimeString,
        ];
    }
}
