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
 * Lists of values stored as comma-separated values (DCA: 'eval' => ['csv' => ',']),
 * e.g. tl_resource_booking_time_slot.weekdays = "monday,tuesday".
 *
 * Until the migration ConvertSerializedListsToCsv has run, the fields can still
 * contain a serialized array: parse() reads both formats.
 */
final class CsvList
{
    public const DELIMITER = ',';

    private function __construct()
    {
    }

    /**
     * Returns the values of a field: a CSV string, a serialized array (not migrated yet)
     * or an array. Empty values are skipped.
     *
     * @return list<string>
     */
    public static function parse(mixed $value): array
    {
        if (null === $value || '' === $value || false === $value) {
            return [];
        }

        // Serialized array (not migrated yet), objects are not allowed
        if (\is_string($value) && str_starts_with($value, 'a:')) {
            $value = StringUtil::deserialize($value);

            if (!\is_array($value)) {
                return [];
            }
        }

        if (!\is_array($value)) {
            $value = explode(self::DELIMITER, (string) $value);
        }

        $values = [];

        foreach ($value as $item) {
            if (\is_scalar($item) && '' !== ($item = trim((string) $item))) {
                $values[] = $item;
            }
        }

        return $values;
    }

    /**
     * @return list<int>
     */
    public static function parseIds(mixed $value): array
    {
        return array_map('intval', self::parse($value));
    }

    /**
     * @param iterable<scalar> $values
     */
    public static function toString(iterable $values): string
    {
        $list = [];

        foreach ($values as $value) {
            if ('' !== ($value = trim((string) $value))) {
                $list[] = $value;
            }
        }

        return implode(self::DELIMITER, $list);
    }
}
