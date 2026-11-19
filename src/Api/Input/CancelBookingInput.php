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

namespace Markocupic\ResourceBookingBundle\Api\Input;

use Symfony\Component\HttpFoundation\Request;

/**
 * Cancellation of a booking: DELETE /_rbb/api/bookings/{id}?deleteRepetitions=1.
 */
final class CancelBookingInput
{
    public function __construct(
        public readonly int $bookingId,
        // Also cancel the repetitions (bookings with the same booking UUID)
        public readonly bool $deleteRepetitions = false,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $id = $request->attributes->get('id');
        // all(): get() throws for arrays (manipulated request)
        $deleteRepetitions = $request->query->all()['deleteRepetitions'] ?? null;

        return new self(
            \is_scalar($id) && preg_match('/^\d+$/', (string) $id) ? (int) $id : 0,
            \is_scalar($deleteRepetitions) && \in_array((string) $deleteRepetitions, ['1', 'true'], true),
        );
    }
}
