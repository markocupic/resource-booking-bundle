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

namespace Markocupic\ResourceBookingBundle\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads the data a slot needs (logged-in member, time slot, timetable, bookings)
 * and keeps it for the current request.
 *
 * Without this class, every slot of the week calendar ran its own queries. Call
 * preloadBookings() before creating the slots of a week: the bookings of the
 * whole week are then fetched with one query.
 */
class SlotDataProvider implements ResetInterface
{
    private bool $memberResolved = false;

    private MemberModel|null $member = null;

    /**
     * @var array<int, ResourceBookingTimeSlotModel|null>
     */
    private array $timeSlots = [];

    /**
     * @var array<int, ResourceBookingTimeSlotTypeModel|null>
     */
    private array $timeSlotTypes = [];

    /**
     * Preloaded bookings per resource: the time range [from, to) and the booking rows
     * grouped by "startTime-endTime".
     *
     * @var array<int, array{from: int, to: int, bookings: array<string, list<array<string, mixed>>>}>
     */
    private array $preloadedBookings = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Connection $connection,
    ) {
    }

    public function getLoggedInMember(): MemberModel|null
    {
        if ($this->memberResolved) {
            return $this->member;
        }

        $this->memberResolved = true;
        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof FrontendUser) {
            $this->member = $this->framework->getAdapter(MemberModel::class)->findById($user->id);
        }

        return $this->member;
    }

    public function getTimeSlot(int $id): ResourceBookingTimeSlotModel|null
    {
        if (!\array_key_exists($id, $this->timeSlots)) {
            $this->timeSlots[$id] = $id > 0 ? $this->framework->getAdapter(ResourceBookingTimeSlotModel::class)->findById($id) : null;
        }

        return $this->timeSlots[$id];
    }

    public function getTimeSlotType(int $id): ResourceBookingTimeSlotTypeModel|null
    {
        if (!\array_key_exists($id, $this->timeSlotTypes)) {
            $this->timeSlotTypes[$id] = $id > 0 ? $this->framework->getAdapter(ResourceBookingTimeSlotTypeModel::class)->findById($id) : null;
        }

        return $this->timeSlotTypes[$id];
    }

    /**
     * Fetches all bookings of the resource that start in the time range [from, to)
     * with one query (e.g. all bookings of the displayed week).
     */
    public function preloadBookings(int $resourceId, int $from, int $to): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_resource_booking WHERE pid = ? AND startTime >= ? AND startTime < ? ORDER BY id',
            [$resourceId, $from, $to],
        );

        $this->preloadedBookings[$resourceId] = [
            'from' => $from,
            'to' => $to,
            'bookings' => self::groupBookingsBySlot($rows),
        ];
    }

    /**
     * Returns the bookings of the resource with exactly this start and end time.
     *
     * @return list<array<string, mixed>>
     */
    public function getBookings(int $resourceId, int $startTime, int $endTime): array
    {
        $preloaded = $this->preloadedBookings[$resourceId] ?? null;

        if (null !== $preloaded && $startTime >= $preloaded['from'] && $startTime < $preloaded['to']) {
            return $preloaded['bookings'][self::getSlotKey($startTime, $endTime)] ?? [];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_resource_booking WHERE pid = ? AND startTime = ? AND endTime = ? ORDER BY id',
            [$resourceId, $startTime, $endTime],
        );
    }

    /**
     * Forgets the preloaded bookings, e.g. after bookings have been saved or deleted.
     */
    public function clearBookings(): void
    {
        $this->preloadedBookings = [];
    }

    public function reset(): void
    {
        $this->memberResolved = false;
        $this->member = null;
        $this->timeSlots = [];
        $this->timeSlotTypes = [];
        $this->preloadedBookings = [];

        // The blocked periods are cached in a static property (see findPublishedRows())
        ResourceBookingBlockedPeriodModel::resetCache();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groupBookingsBySlot(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[self::getSlotKey((int) $row['startTime'], (int) $row['endTime'])][] = $row;
        }

        return $grouped;
    }

    private static function getSlotKey(int $startTime, int $endTime): string
    {
        return $startTime.'-'.$endTime;
    }
}
