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

use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;
use Markocupic\ResourceBookingBundle\Util\CsvList;

/**
 * A blocked period (tl_resource_booking_blocked_period), e.g. holidays.
 *
 * Mode "period": the period applies to all resources, to resource types or to
 * resources (scope) and blocks the days from dateFrom to dateTo, optionally only
 * the blocked times. Mode "slots": single time slots of one resource are blocked
 * (selected in the calendar view of the back end).
 *
 * Immutable value object, created from the row.
 */
final class BlockedPeriod
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_RESOURCE_TYPES = 'resourceTypes';

    public const SCOPE_RESOURCES = 'resources';

    /**
     * @param string                                   $title           Internal name
     * @param string                                   $description     Note displayed in the week calendar
     * @param int                                      $resourceId      The resource of the mode "slots"
     * @param list<int>                                $resourceTypeIds Scope "resourceTypes"
     * @param list<int>                                $resourceIds     Scope "resources"
     * @param int                                      $dateFrom        First blocked day
     * @param int|null                                 $dateTo          Last blocked day, null: unlimited
     * @param list<BlockedTime>                        $blockedTimes    Empty: the whole days are blocked
     * @param list<array{timeSlot: int, date: string}> $blockedSlots    Mode "slots"
     */
    public function __construct(
        public readonly int $id = 0,
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly bool $published = true,
        public readonly string $mode = ResourceBookingBlockedPeriodModel::MODE_PERIOD,
        public readonly int $resourceId = 0,
        public readonly string $scope = self::SCOPE_ALL,
        public readonly array $resourceTypeIds = [],
        public readonly array $resourceIds = [],
        public readonly int $dateFrom = 0,
        public readonly int|null $dateTo = null,
        public readonly array $blockedTimes = [],
        public readonly array $blockedSlots = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row Row of tl_resource_booking_blocked_period
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            title: (string) ($row['title'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            published: !empty($row['published']),
            mode: (string) ($row['mode'] ?? null ?: ResourceBookingBlockedPeriodModel::MODE_PERIOD),
            resourceId: (int) ($row['resource'] ?? 0),
            scope: (string) ($row['scope'] ?? null ?: self::SCOPE_ALL),
            resourceTypeIds: CsvList::parseIds($row['resourceTypes'] ?? null),
            resourceIds: CsvList::parseIds($row['resources'] ?? null),
            dateFrom: (int) ($row['dateFrom'] ?? 0),
            dateTo: empty($row['dateTo']) ? null : (int) $row['dateTo'],
            blockedTimes: BlockedTime::listFromSerialized($row['blockedTimes'] ?? null),
            blockedSlots: BlockedSlots::fromPeriod($row),
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<self>
     */
    public static function listFromRows(array $rows): array
    {
        return array_values(array_map(self::fromRow(...), $rows));
    }

    public function isSlotsMode(): bool
    {
        return ResourceBookingBlockedPeriodModel::MODE_SLOTS === $this->mode;
    }

    /**
     * Returns true if the period applies to the resource: in the mode "slots" its
     * resource, otherwise the scope ("all", the resource type or the resource).
     */
    public function appliesTo(ResourceRules $resource): bool
    {
        if ($this->isSlotsMode()) {
            return $this->resourceId === $resource->id;
        }

        return match ($this->scope) {
            self::SCOPE_RESOURCE_TYPES => \in_array($resource->resourceTypeId, $this->resourceTypeIds, true),
            self::SCOPE_RESOURCES => \in_array($resource->id, $this->resourceIds, true),
            default => true,
        };
    }

    /**
     * Returns true if the published period applies to the resource and covers the
     * slot from $startTime to $endTime.
     *
     * The slot is covered if its day lies between dateFrom and dateTo (inclusive)
     * and, if the period has blocked times, if the slot overlaps one of the blocked
     * times on its weekday. Without blocked times the whole days are blocked.
     *
     * In the mode "slots" the slot is covered if its time slot ($timeSlotId) is
     * blocked on the day of the slot. Without time slot ID the slot is never covered.
     */
    public function covers(ResourceRules $resource, int $startTime, int $endTime, int $timeSlotId = 0): bool
    {
        if (!$this->published || !$this->appliesTo($resource)) {
            return false;
        }

        if ($this->isSlotsMode()) {
            return BlockedSlots::contains($this->blockedSlots, $timeSlotId, $startTime);
        }

        $dayStart = strtotime(date('Y-m-d', $startTime));

        if ($dayStart < strtotime(date('Y-m-d', $this->dateFrom))) {
            return false;
        }

        if (null !== $this->dateTo && $dayStart > strtotime(date('Y-m-d', $this->dateTo))) {
            return false;
        }

        if ([] === $this->blockedTimes) {
            return true;
        }

        $weekday = strtolower(date('l', $startTime));

        foreach ($this->blockedTimes as $blockedTime) {
            if ($blockedTime->overlaps($weekday, $startTime - $dayStart, $endTime - $dayStart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The name in the back end: the title (internal name), otherwise the description or the ID.
     */
    public function getLabel(): string
    {
        return trim($this->title) ?: (trim($this->description) ?: '#'.$this->id);
    }
}
