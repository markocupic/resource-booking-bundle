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
     * Maximum number of time slots that can be selected in one booking request.
     */
    public const MAX_SELECTED_SLOTS = 100;

    /**
     * System fields of the booking request and their expected type.
     */
    public const FIELD_TYPES = [
        // resType, res and date: state of the week calendar, validated by the AppStateFactory
        'string' => ['REQUEST_TOKEN', 'action', 'moduleKey', 'resType', 'res', 'date', 'bookingDescription'],
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
        'bookingSource',
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

                if ('bookingDateSelection' === $key) {
                    self::parseSlotSelection($value);
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

    /**
     * Parses the selected time slots of a booking request. Every entry has the format
     * "timeSlotId-startTime-endTime-beginWeekTimestamp" (see WeekTableBuilder::getBookingCheckboxValue()).
     *
     * @return list<array{timeSlotId: int, startTime: int, endTime: int}>
     *
     * @throws InvalidBookingRequestException if the value is not a list of valid entries or contains too many entries
     */
    public static function parseSlotSelection(mixed $selection): array
    {
        if (null === $selection || '' === $selection || [] === $selection) {
            return [];
        }

        if (!\is_array($selection) || \count($selection) > self::MAX_SELECTED_SLOTS) {
            throw InvalidBookingRequestException::invalidValue('bookingDateSelection');
        }

        $slots = [];

        foreach ($selection as $entry) {
            if (!\is_string($entry) || !preg_match('/^(\d+)-(\d+)-(\d+)-\d+$/', $entry, $matches)) {
                throw InvalidBookingRequestException::invalidValue('bookingDateSelection');
            }

            [, $timeSlotId, $startTime, $endTime] = array_map('intval', $matches);

            if ($timeSlotId < 1 || $endTime <= $startTime) {
                throw InvalidBookingRequestException::invalidValue('bookingDateSelection');
            }

            $slots[] = ['timeSlotId' => $timeSlotId, 'startTime' => $startTime, 'endTime' => $endTime];
        }

        return $slots;
    }
}
