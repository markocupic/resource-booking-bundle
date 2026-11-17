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

use Contao\StringUtil;

/**
 * Single blocked slots of a blocked period in the mode "slots"
 * (tl_resource_booking_blocked_period.blockedSlots): a list of
 * ['timeSlot' => id of tl_resource_booking_time_slot, 'date' => 'Y-m-d'].
 *
 * The times are not stored: they are always read from the time slot. If the
 * times of the time slot change, the block moves with it.
 *
 * In the back end form the slots are transferred as a compact string
 * "<timeSlot>:<Y-m-d>,…" (see BlockedSlotsWidget).
 */
final class BlockedSlots
{
    /**
     * @var array<string, list<array{timeSlot: int, date: string}>>
     */
    private static array $cache = [];

    private function __construct()
    {
    }

    /**
     * Returns the blocked slots of a blocked period (cached per record version).
     *
     * @param array<string, mixed> $period
     *
     * @return list<array{timeSlot: int, date: string}>
     */
    public static function fromPeriod(array $period): array
    {
        $raw = $period['blockedSlots'] ?? null;

        if (empty($raw)) {
            return [];
        }

        if (\is_array($raw)) {
            return self::normalize($raw);
        }

        $key = ($period['id'] ?? '').':'.($period['tstamp'] ?? '').':'.md5((string) $raw);

        return self::$cache[$key] ??= self::normalize(StringUtil::deserialize($raw, true));
    }

    /**
     * Parses the compact string of the back end form, invalid entries are skipped.
     *
     * @return list<array{timeSlot: int, date: string}>
     */
    public static function parse(string $value): array
    {
        $slots = [];

        foreach (explode(',', $value) as $item) {
            if (!preg_match('/^\s*(\d+):(\d{4}-\d{2}-\d{2})\s*$/', $item, $matches)) {
                continue;
            }

            $slots[] = ['timeSlot' => (int) $matches[1], 'date' => $matches[2]];
        }

        return self::normalize($slots);
    }

    /**
     * @param list<array{timeSlot: int, date: string}> $slots
     */
    public static function toString(array $slots): string
    {
        return implode(',', array_map(self::key(...), self::normalize($slots)));
    }

    /**
     * The value of a slot in the back end form, e.g. "7:2026-11-20".
     *
     * @param array{timeSlot: int, date: string} $slot
     */
    public static function key(array $slot): string
    {
        return $slot['timeSlot'].':'.$slot['date'];
    }

    /**
     * Removes invalid entries (no time slot, invalid date) and duplicates and
     * sorts the slots by date and time slot.
     *
     * @param array<mixed> $slots
     *
     * @return list<array{timeSlot: int, date: string}>
     */
    public static function normalize(array $slots): array
    {
        $normalized = [];

        foreach ($slots as $slot) {
            if (!\is_array($slot)) {
                continue;
            }

            $timeSlot = (int) ($slot['timeSlot'] ?? 0);
            $date = (string) ($slot['date'] ?? '');

            if ($timeSlot < 1 || !self::isValidDate($date)) {
                continue;
            }

            $normalized[$date.':'.str_pad((string) $timeSlot, 10, '0', STR_PAD_LEFT)] = ['timeSlot' => $timeSlot, 'date' => $date];
        }

        ksort($normalized);

        return array_values($normalized);
    }

    /**
     * Returns true if the time slot is blocked on the day of $startTime.
     *
     * @param list<array{timeSlot: int, date: string}> $slots
     */
    public static function contains(array $slots, int $timeSlotId, int $startTime): bool
    {
        if ($timeSlotId < 1) {
            return false;
        }

        $date = date('Y-m-d', $startTime);

        foreach ($slots as $slot) {
            if ($slot['timeSlot'] === $timeSlotId && $slot['date'] === $date) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the first and the last day (midnight) of the blocked slots, or null without slots.
     * The values are stored in dateFrom and dateTo, e.g. for the list view and the search
     * of the published blocked periods.
     *
     * @param list<array{timeSlot: int, date: string}> $slots
     *
     * @return array{0: int, 1: int}|null
     */
    public static function getDateRange(array $slots): array|null
    {
        if (empty($slots)) {
            return null;
        }

        $dates = array_column($slots, 'date');

        return [(int) strtotime(min($dates)), (int) strtotime(max($dates))];
    }

    private static function isValidDate(string $date): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
