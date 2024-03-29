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

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

class ResourceBookingResourceModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_resource';

    public static function findPublishedByPid(int $intId): Collection|null
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
     * @throws \Exception
     */
    public static function findPublishedByPk(int $intId): static|null
    {
        $arrColumn = ['id=?', 'published=?'];
        $arrValues = [$intId, 1];
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
     * @throws \Exception
     */
    public static function findPublishedByPkAndPid(int $intId, int $intPid): static|null
    {
        $arrColumn = ['id=?', 'pid=?', 'published=?'];
        $arrValues = [$intId, $intPid, 1];

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

    public static function findPublishedByIds(array $arrIds): Collection|null
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

    public static function findMultipleAndPublishedByPids(array $arrPids): Collection|null
    {
        if (empty($arrPids)) {
            return null;
        }

        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking_resource WHERE pid IN('.implode(',', $arrPids).') AND published=?')->execute(1);
        $arrIds = $objDb->fetchEach('id');

        return static::findMultipleByIds($arrIds);
    }
}
