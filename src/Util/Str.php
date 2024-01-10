<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
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
        $str = (string) $varData;
        // Convert bin uuids to string uuids
        if (!empty($str) && !preg_match('//u', $str)) {
            if (\is_array(StringUtil::deserialize($str))) {
                $arrTemp = [];

                foreach (StringUtil::deserialize($str) as $strUuid) {
                    if (Validator::isBinaryUuid($strUuid)) {
                        $arrTemp[] = StringUtil::binToUuid($strUuid);
                    }
                }
                $str = serialize($arrTemp);
            } else {
                $strTemp = '';

                if (Validator::isBinaryUuid($str)) {
                    $strTemp = StringUtil::binToUuid($str);
                }
                $str = $strTemp;
            }
        }

        return $str;
    }
}
