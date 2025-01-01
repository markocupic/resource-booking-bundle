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
use Contao\Validator;

class Str
{

    public static function convertBinUuidsToStringUuids($varData): string
    {
        $stringData = (string) $varData;

        // Early return if empty or already UTF-8
        if (self::isEmptyOrUtf8($stringData)) {
            return $stringData;
        }

        $deserializedData = StringUtil::deserialize($stringData);

        if (is_array($deserializedData)) {
            return self::processArrayUuids($deserializedData);
        }

        return Validator::isBinaryUuid($stringData) ? StringUtil::binToUuid($stringData) : '';
    }

    private static function isEmptyOrUtf8(string $data): bool
    {
        return empty($data) || preg_match('//u', $data);
    }

    private static function processArrayUuids(array $uuids): string
    {
        $convertedUuids = array_map([self::class, 'convertBinaryUuid'], $uuids);

        // Remove null values for non-valid binary UUIDs
        $filteredUuids = array_filter($convertedUuids);

        return serialize($filteredUuids);
    }

    private static function convertBinaryUuid(?string $uuid): ?string
    {
        return Validator::isBinaryUuid($uuid) ? StringUtil::binToUuid($uuid) : null;
    }
}
