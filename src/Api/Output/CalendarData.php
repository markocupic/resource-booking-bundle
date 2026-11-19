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

namespace Markocupic\ResourceBookingBundle\Api\Output;

/**
 * Data of the week calendar (GET /_rbb/api/calendar): filter, active week,
 * active resource and the table.
 *
 * Only the fields the front end needs are sent, never complete rows of the
 * database: the member, the resource and the resource types are reduced to a
 * few fields in toArray(). Further data can be added with the AjaxResponseEvent.
 */
final class CalendarData implements ApiResultInterface
{
    /**
     * Fields of the active resource sent to the front end.
     */
    public const RESOURCE_FIELDS = ['id', 'title', 'description', 'itemsAvailable'];

    /**
     * Fields of a resource type sent to the front end.
     */
    public const RESOURCE_TYPE_FIELDS = ['id', 'title'];

    /**
     * Fields of the logged-in member sent to the front end.
     */
    public const MEMBER_FIELDS = ['firstname', 'lastname'];

    /**
     * @param list<array<string, mixed>>         $resourceTypes           Rows of the resource types of the module
     * @param list<array<string, mixed>>         $resources               Rows of the resources of the active resource type
     * @param array{disabled: bool, tstamp: int} $jumpPrevWeek
     * @param array{disabled: bool, tstamp: int} $jumpNextWeek
     * @param list<array<string, mixed>>         $weekSelection           Options of the week dropdown
     * @param array<string, mixed>|null          $member                  Row of the logged-in member
     * @param array<string, mixed>               $activeWeek              Dates and texts of the active week
     * @param list<array<string, mixed>>         $bookingRepeatsSelection Options of the repetitions in the booking window
     * @param list<array<string, mixed>>         $weekdays                Header of the table
     * @param array<string, mixed>|null          $activeResourceType      Row of the active resource type
     * @param array<string, mixed>|null          $activeResource          Row of the active resource
     * @param list<array<string, mixed>>         $rows                    Rows of the table (WeekTableBuilder)
     * @param list<array<string, mixed>>         $timeSlots               First column of the table (WeekTableBuilder)
     */
    public function __construct(
        public readonly array $resourceTypes,
        public readonly array $resources,
        public readonly array $jumpPrevWeek,
        public readonly array $jumpNextWeek,
        public readonly array $weekSelection,
        public readonly int $firstPermittedWeekStart,
        public readonly int $lastPermittedWeekStart,
        public readonly array|null $member,
        public readonly int $activeWeekStart,
        public readonly array $activeWeek,
        public readonly array $bookingRepeatsSelection,
        public readonly array $weekdays,
        public readonly array|null $activeResourceType,
        public readonly array|null $activeResource,
        public readonly array $rows,
        public readonly array $timeSlots,
    ) {
    }

    public function toArray(): array
    {
        return [
            'filterBoard' => [
                'resourceTypes' => array_map(static fn (array $row): array => self::pick($row, self::RESOURCE_TYPE_FIELDS), $this->resourceTypes),
                'resources' => array_map(static fn (array $row): array => self::pick($row, self::RESOURCE_FIELDS), $this->resources),
                'jumpPrevWeek' => $this->jumpPrevWeek,
                'jumpNextWeek' => $this->jumpNextWeek,
                'weekSelection' => $this->weekSelection,
                // The front end does not navigate outside these weeks (swipe, keyboard)
                'firstPermittedWeekStart' => $this->firstPermittedWeekStart,
                'lastPermittedWeekStart' => $this->lastPermittedWeekStart,
            ],
            'hasLoggedInUser' => null !== $this->member,
            'loggedInUser' => null === $this->member ? null : self::pick($this->member, self::MEMBER_FIELDS),
            'activeWeekStart' => $this->activeWeekStart,
            'activeWeek' => $this->activeWeek,
            'bookingRepeatsSelection' => $this->bookingRepeatsSelection,
            'weekdays' => $this->weekdays,
            // Always sent: the front end must not keep the previous selection after a filter change
            'activeResourceType' => null === $this->activeResourceType ? [] : self::pick($this->activeResourceType, self::RESOURCE_TYPE_FIELDS),
            'activeResourceTypeId' => null === $this->activeResourceType ? 'undefined' : (int) $this->activeResourceType['id'],
            'activeResource' => null === $this->activeResource ? [] : self::pick($this->activeResource, self::RESOURCE_FIELDS),
            'activeResourceId' => null === $this->activeResource ? 'undefined' : (int) $this->activeResource['id'],
            'rows' => $this->rows,
            'timeSlots' => $this->timeSlots,
            'isReady' => true,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $fields
     *
     * @return array<string, mixed>
     */
    private static function pick(array $row, array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            $data[$field] = 'id' === $field || 'itemsAvailable' === $field ? (int) ($row[$field] ?? 0) : (string) ($row[$field] ?? '');
        }

        return $data;
    }
}
