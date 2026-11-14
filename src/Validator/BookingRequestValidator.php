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

namespace Markocupic\ResourceBookingBundle\Validator;

/**
 * Validates the POST data of a booking request sent by the front end.
 *
 * Only the system fields listed in FIELD_TYPES and the permitted form fields
 * of the app configuration (apps.<name>.permitted_form_fields) are accepted.
 * The fields in PROTECTED_BOOKING_FIELDS are set by the server and can never
 * be submitted from the front end, not even if they are added to the
 * permitted form fields.
 */
final class BookingRequestValidator
{
    /**
     * System fields of the booking request and their expected type.
     */
    public const FIELD_TYPES = [
        'string' => ['REQUEST_TOKEN', 'action', 'moduleKey', 'bookingDescription'],
        'array' => ['bookingDateSelection'],
        'integer' => ['resourceId', 'bookingRepeatStopWeekTstamp'],
    ];

    /**
     * Fields of tl_resource_booking that are always set by the server.
     */
    public const PROTECTED_BOOKING_FIELDS = [
        'id',
        'pid',
        'tstamp',
        'member',
        'isBlocked',
        'confirmed',
        'upcoming',
        'timeSlotId',
        'startTime',
        'endTime',
        'bookingTime',
        'bookingUuid',
        'moduleId',
        'title',
    ];

    /**
     * @param array<string, mixed> $post
     * @param array<int, string>   $permittedFormFields
     *
     * @throws InvalidBookingRequestException
     */
    public function validate(array $post, array $permittedFormFields): void
    {
        foreach ($post as $key => $value) {
            $key = (string) $key;

            if (\in_array($key, self::PROTECTED_BOOKING_FIELDS, true)) {
                throw InvalidBookingRequestException::fieldNotPermitted($key);
            }

            if (\in_array($key, self::FIELD_TYPES['string'], true)) {
                if (!empty($value) && !\is_string($value)) {
                    throw InvalidBookingRequestException::invalidValue($key);
                }
            } elseif (\in_array($key, self::FIELD_TYPES['array'], true)) {
                if (!\is_array($value)) {
                    throw InvalidBookingRequestException::invalidValue($key);
                }
            } elseif (\in_array($key, self::FIELD_TYPES['integer'], true)) {
                if (!\is_string($value) || !preg_match('/^-?\d+$/', $value) || 0 === (int) $value) {
                    throw InvalidBookingRequestException::invalidValue($key);
                }
            } elseif (!\in_array($key, $permittedFormFields, true)) {
                throw InvalidBookingRequestException::fieldNotPermitted($key);
            }
        }
    }

    /**
     * Returns true if the value is a valid number of items: an integer between 1
     * and the number of available items of the resource.
     */
    public static function isValidItemCount(mixed $itemsBooked, int $itemsAvailable): bool
    {
        if (\is_string($itemsBooked) && preg_match('/^\d+$/', $itemsBooked)) {
            $itemsBooked = (int) $itemsBooked;
        }

        return \is_int($itemsBooked) && $itemsBooked >= 1 && $itemsBooked <= $itemsAvailable;
    }

    /**
     * Converts the submitted number of items: an empty value means one item,
     * anything that is not a positive integer becomes 0 (invalid).
     */
    public static function parseItemCount(mixed $itemsBooked): int
    {
        if (null === $itemsBooked || '' === $itemsBooked) {
            return 1;
        }

        if (\is_int($itemsBooked)) {
            return max(0, $itemsBooked);
        }

        return \is_string($itemsBooked) && preg_match('/^\d+$/', $itemsBooked) ? (int) $itemsBooked : 0;
    }
}
