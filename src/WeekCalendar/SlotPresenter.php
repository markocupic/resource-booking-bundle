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
 * booking preview): the results of the booking rules of the slot and the texts
 * for the display (date and times).
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

        $startTime = (int) $slot->startTime;
        $endTime = (int) $slot->endTime;
        $startTimeString = UtcTimeHelper::parseStartTime($startTime);
        $endTimeString = UtcTimeHelper::parseEndTime($endTime);
        $dateString = $dateAdapter->parse((string) $this->framework->getAdapter(Config::class)->get('dateFormat'), $startTime);

        return [
            ...$slot->row(),
            'startTimeString' => $startTimeString,
            'endTimeString' => $endTimeString,
            'date' => $dateString,
            // E.g. "Mo, 16.11.2026: 08:00 - 09:00"
            'datimSpanString' => \sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $startTime), $dateString, $startTimeString, $endTimeString),
            'timeSpanString' => $startTimeString.' - '.$endTimeString,
        ];
    }
}
