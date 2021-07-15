<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Model;
use Contao\Model\Collection;

/**
 * Class ResourceBookingModel.
 */
class ResourceBookingModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking';

    public static function findByIds(array $arrIds, array $arrOptions = []): ?Collection
    {
        if (empty($arrIds)) {
            return null;
        }

        $t = static::$strTable;

        return static::findBy(["$t.id IN(".implode(',', array_map('\intval', $arrIds)).')'], null, $arrOptions);
    }

    /**
     * @param $objResource
     * @param $starttime
     * @param $endtime
     *
     * @return ResourceBookingModel
     */
    public static function findOneByResourceIdStarttimeAndEndtime($objResource, $starttime, $endtime): ?self
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?'];
        $arrValues = [$objResource->id, $starttime, $endtime];

        return self::findOneBy($arrColumn, $arrValues);
    }

    /**
     * @param $objResource
     * @param $starttime
     * @param $endtime
     */
    public static function findByResourceStarttimeAndEndtime($objResource, $starttime, $endtime): ?Collection
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?'];
        $arrValues = [$objResource->id, $starttime, $endtime];

        return self::findBy($arrColumn, $arrValues);
    }

    /**
     * @param $objResource
     * @param $starttime
     * @param $endtime
     * @param $memberid
     *
     * @return ResourceBookingModel
     */
    public static function findOneByResourceIdStarttimeEndtimeAndMember($objResource, $starttime, $endtime, $memberid): ?self
    {
        $arrColumn = ['pid=?', 'startTime=?', 'endTime=?', 'member=?'];
        $arrValues = [$objResource->id, $starttime, $endtime, $memberid];

        return self::findOneBy($arrColumn, $arrValues);
    }
}
