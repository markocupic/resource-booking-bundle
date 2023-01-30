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

class ResourceBookingModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking';

    public static function findByIds(array $arrIds, array $arrOptions = []): Collection|null
    {
        if (empty($arrIds)) {
            return null;
        }

        $t = static::$strTable;

        return static::findBy(["$t.id IN(".implode(',', array_map('\intval', $arrIds)).')'], null, $arrOptions);
    }

    /**
     * @return static|null
     */
    public static function findOneByResourceIdStartTimeAndEndTime(ResourceBookingResourceModel $objResource, int $startTime, int $endTime): self|null
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?'];
        $arrValues = [$objResource->id, $startTime, $endTime];

        return self::findOneBy($arrColumn, $arrValues);
    }

    public static function findByResourceStartTimeAndEndTime(ResourceBookingResourceModel $objResource, int $startTime, int $endTime): Collection|null
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?'];
        $arrValues = [$objResource->id, $startTime, $endTime];

        return self::findBy($arrColumn, $arrValues);
    }

    /**
     * @return static|null
     */
    public static function findOneByResourceIdStartTimeEndTimeAndMember(ResourceBookingResourceModel $objResource, int $startTime, int $endTime, int $memberId): self|null
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?', 'member=?'];
        $arrValues = [$objResource->id, $startTime, $endTime, $memberId];

        return self::findOneBy($arrColumn, $arrValues);
    }
}
