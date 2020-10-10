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

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

/**
 * Class ResourceBookingResourceModel.
 */
class ResourceBookingResourceModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_resource';

    /**
     * @param $intId
     *
     * @return Collection|null
     */
    public static function findPublishedByPid($intId)
    {
        $arrIds = [];
        $objDb = static::findByPid($intId);

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

        return static::findMultipleByIds($arrIds);
    }

    /**
     * @param $intId
     *
     * @throws \Exception
     *
     * @return ResourceBookingResourceModel|null
     */
    public static function findPublishedByPk($intId)
    {
        $arrColumn = ['id=?', 'published=?'];
        $arrValues = [$intId, '1'];
        $objDb = self::findOneBy($arrColumn, $arrValues);

        if (null !== $objDb) {
            // Return if parent is published too
            $objParent = $objDb->getRelated('pid');

            if (null !== $objParent) {
                if ($objParent->published) {
                    return $objDb;
                }
            }
        }

        return null;
    }

    /**
     * @param $intId
     * @param $intPid
     *
     * @throws \Exception
     *
     * @return ResourceBookingResourceModel|null
     */
    public static function findPublishedByPkAndPid($intId, $intPid)
    {
        $arrColumn = ['id=?', 'pid=?', 'published=?'];
        $arrValues = [$intId, $intPid, '1'];

        $objDb = self::findOneBy($arrColumn, $arrValues);

        if (null !== $objDb) {
            // Return if parent is published too
            $objParent = $objDb->getRelated('pid');

            if (null !== $objParent) {
                if ($objParent->published) {
                    return $objDb;
                }
            }
        }

        return null;
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
                    // Return if parent is published too
                    $objParent = $objDb->getRelated('pid');

                    if (null !== $objParent) {
                        if ($objParent->published) {
                            $arrIdsNew[] = $objDb->id;
                        }
                    }
                }
            }
        }

        return static::findMultipleByIds($arrIdsNew);
    }

    /**
     * @return Collection|null
     */
    public static function findMultipleAndPublishedByPids(array $arrPids)
    {
        if (empty($arrPids)) {
            return null;
        }

        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking_resource WHERE pid IN('.implode(',', $arrPids).') AND published=?')->execute('1');
        $arrIds = $objDb->fetchEach('id');

        return static::findMultipleByIds($arrIds);
    }
}
