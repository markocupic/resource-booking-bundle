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

use Contao\Model;
use Contao\Model\Collection;

class ResourceBookingResourceTypeModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_resource_type';

    public static function findPublishedByPk(int $intId): static|null
    {
        $arrColumn = ['id=?', 'published=?'];
        $arrValues = [$intId, 1];

        return self::findOneBy($arrColumn, $arrValues);
    }

    public static function findPublishedByIds(array $arrIds): Collection|null
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
