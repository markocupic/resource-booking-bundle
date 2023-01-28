<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Model;
use Contao\Model\Collection;

class ResourceBookingTimeSlotModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_time_slot';

    public static function findPublishedByPid(int $intPid): ?Collection
    {
        $arrIds = [];

        $objDb = static::findByPid($intPid);

        if (null !== $objDb) {
            while ($objDb->next()) {
                if ($objDb->published) {
                    // Return if parent is published too
                    $objParent = $objDb->getRelated('pid');

                    if (null !== $objParent) {
                        if ($objParent->published) {
                            $arrIds[] = $objDb->id;
                        }
                    }
                }
            }
        }

        $arrOptions = [
            'order' => 'tl_resource_booking_time_slot.sorting ASC',
        ];

        return static::findMultipleByIds($arrIds, $arrOptions);
    }
}
