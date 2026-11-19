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

use Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Booking request and booking preview (POST /_rbb/api/bookings and
 * POST /_rbb/api/bookings/validate).
 *
 * POST fields:
 *
 *  - resourceId: the resource to book
 *  - slots[]: selected time slots, "timeSlotId-startTime-endTime-weekStart"
 *  - requestedItems: number of items (empty: 1)
 *  - repeatUntil: first day of the last week of the weekly repetitions
 *  - bookingDescription: short description (optional)
 *  - resType, res, date: selection of the week calendar (see CalendarQuery)
 *  - custom form fields: only if permitted in the app configuration
 *    (markocupic_resource_booking.apps.<name>.permitted_form_fields)
 */
final class BookingInput
{
    /**
     * @param list<SelectedSlot>   $slots
     * @param array<string, mixed> $formFields Values of the permitted custom form fields, not encoded yet
     */
    public function __construct(
        public readonly int $resourceId,
        public readonly array $slots,
        // 0 if the value is not a positive integer: the slot rejects it (Slot::hasValidItemCount())
        public readonly int $requestedItems,
        public readonly int $repeatUntil,
        public readonly string $bookingDescription = '',
        public readonly array $formFields = [],
    ) {
    }

    /**
     * @param list<string> $permittedFormFields Custom form fields of the app configuration
     *
     * @throws InvalidBookingRequestException if a field is not permitted or has an invalid value
     */
    public static function fromRequest(Request $request, array $permittedFormFields): self
    {
        // all(): the input bag does not return arrays with get() (slots[])
        $post = $request->request->all();

        (new BookingRequestValidator())->validate($post, $permittedFormFields);

        foreach (['resourceId', 'repeatUntil'] as $required) {
            if (!isset($post[$required])) {
                throw InvalidBookingRequestException::invalidValue($required);
            }
        }

        $systemFields = array_merge(...array_values(BookingRequestValidator::FIELD_TYPES));

        return new self(
            (int) $post['resourceId'],
            array_map(
                static fn (array $slot): SelectedSlot => new SelectedSlot($slot['timeSlotId'], $slot['startTime'], $slot['endTime']),
                BookingRequestValidator::parseSlotSelection($post['slots'] ?? null),
            ),
            BookingRequestValidator::parseItemCount($post['requestedItems'] ?? null),
            (int) $post['repeatUntil'],
            (string) ($post['bookingDescription'] ?? ''),
            array_diff_key($post, array_flip($systemFields)),
        );
    }
}
