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

namespace Markocupic\ResourceBookingBundle\Restriction;

use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Util\TimeOfDay;

/**
 * A blocked time of a blocked period (a row of tl_resource_booking_blocked_period.blockedTimes):
 * a weekday ("daily" or e.g. "monday") and a time range in seconds since midnight.
 */
final class BlockedTime
{
    public const DAILY = 'daily';

    private const SECONDS_IN_DAY = 86400;

    public function __construct(
        public readonly string $weekday,
        public readonly int $timeFrom = 0,
        public readonly int $timeTo = self::SECONDS_IN_DAY,
    ) {
    }

    /**
     * Reads the blocked times of tl_resource_booking_blocked_period.blockedTimes.
     * Rows with an invalid weekday are skipped. An empty time means the start or
     * the end of the day.
     *
     * @return list<self>
     */
    public static function listFromSerialized(mixed $value): array
    {
        $blockedTimes = [];

        foreach (StringUtil::deserialize($value, true) as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $weekday = strtolower((string) ($row['weekday'] ?? ''));

            if (self::DAILY !== $weekday && !\in_array($weekday, RbbConfig::RBB_WEEKDAYS, true)) {
                continue;
            }

            $blockedTimes[] = new self(
                $weekday,
                TimeOfDay::toSeconds($row['timeFrom'] ?? null) ?? 0,
                TimeOfDay::toSeconds($row['timeTo'] ?? null) ?? self::SECONDS_IN_DAY,
            );
        }

        return $blockedTimes;
    }

    public function isWholeDay(): bool
    {
        return 0 === $this->timeFrom && self::SECONDS_IN_DAY === $this->timeTo;
    }

    /**
     * Returns true if the blocked time applies to the weekday (e.g. "monday") and
     * overlaps the time range (seconds since midnight).
     */
    public function overlaps(string $weekday, int $from, int $to): bool
    {
        if (self::DAILY !== $this->weekday && strtolower($weekday) !== $this->weekday) {
            return false;
        }

        return $from < $this->timeTo && $to > $this->timeFrom;
    }
}
