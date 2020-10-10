<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Model;
use Contao\Model\Collection;

/**
 * Class ResourceBookingResourceTypeModel.
 */
class ResourceBookingResourceTypeModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_resource_type';

    /**
     * @param $intId
     *
     * @return ResourceBookingResourceTypeModel
     */
    public static function findPublishedByPk($intId)
    {
        $arrColumn = ['id=?', 'published=?'];
        $arrValues = [$intId, '1'];

        return self::findOneBy($arrColumn, $arrValues);
    }

    /**
     * @param $arrIds
     *
     * @return Collection|null
     */
    public static function findPublishedByIds($arrIds)
    {
        $arrIdsNew = [];

        if (null !== ($objDb = static::findMultipleByIds($arrIds))) {
            while ($objDb->next()) {
                if ($objDb->published) {
                    $arrIdsNew[] = $objDb->id;
                }
            }
        }

        return static::findMultipleByIds($arrIdsNew);
    }
}
