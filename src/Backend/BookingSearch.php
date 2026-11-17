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

namespace Markocupic\ResourceBookingBundle\Backend;

use Doctrine\DBAL\Connection;

/**
 * Finds the bookings of a booking uuid (tl_resource_booking.bookingUuid). All bookings
 * of a booking request share the same uuid, e.g. the dates of a repeated booking.
 */
class BookingSearch
{
    /**
     * Minimum length of a part of a uuid (e.g. the first block of "0f8f2c4e-…").
     */
    public const MIN_LENGTH = 8;

    public const MAX_RESULTS = 50;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Normalizes the search term: trimmed, lowercase. Returns null if it is not
     * a (part of a) uuid, so nothing is searched for arbitrary input.
     */
    public static function normalize(string $term): string|null
    {
        $term = strtolower(trim($term));

        if (\strlen($term) < self::MIN_LENGTH || \strlen($term) > 64 || !preg_match('/^[a-z0-9-]+$/', $term)) {
            return null;
        }

        return $term;
    }

    /**
     * Bookings whose uuid starts with the search term, ordered by date.
     *
     * @return array{total: int, bookings: list<array<string, mixed>>}
     */
    public function search(string $term, int $limit = self::MAX_RESULTS): array
    {
        if (null === ($term = self::normalize($term))) {
            return ['total' => 0, 'bookings' => []];
        }

        // The term only contains [a-z0-9-], no LIKE wildcards to escape
        $pattern = $term.'%';

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_resource_booking WHERE bookingUuid LIKE ?', [$pattern]);

        if (0 === $total) {
            return ['total' => 0, 'bookings' => []];
        }

        $bookings = $this->connection->fetchAllAssociative(
            'SELECT b.id, b.pid, b.title, b.bookingUuid, b.startTime, b.endTime, b.itemsBooked, b.confirmed, b.member,
                    r.title AS resourceTitle, t.title AS resourceTypeTitle, m.firstname AS memberFirstname, m.lastname AS memberLastname
            FROM tl_resource_booking b
            LEFT JOIN tl_resource_booking_resource r ON r.id = b.pid
            LEFT JOIN tl_resource_booking_resource_type t ON t.id = r.pid
            LEFT JOIN tl_member m ON m.id = b.member
            WHERE b.bookingUuid LIKE ?
            ORDER BY b.startTime, b.id
            LIMIT '.max(1, $limit),
            [$pattern],
        );

        return ['total' => $total, 'bookings' => $bookings];
    }
}
