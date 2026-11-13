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

namespace Markocupic\ResourceBookingBundle\Model;

use Contao\Model;

class ResourceBookingBlockedPeriodModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_resource_booking_blocked_period';

    /**
     * Published blocked periods of the current request, see findPublishedRows().
     *
     * @var array<int, array<string, mixed>>|null
     */
    private static array|null $publishedRows = null;

    /**
     * Returns the rows of all published blocked periods that ended at most one year ago.
     *
     * The result is cached for the current request, because the week calendar
     * checks every slot against the blocked periods.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function findPublishedRows(): array
    {
        if (null !== self::$publishedRows) {
            return self::$publishedRows;
        }

        self::$publishedRows = [];

        $t = static::$strTable;

        $collection = static::findBy(
            [$t.'.published = ?', $t.'.dateTo >= ?'],
            [1, strtotime('today -1 year')],
            ['order' => $t.'.dateFrom ASC'],
        );

        if (null !== $collection) {
            foreach ($collection as $model) {
                self::$publishedRows[] = $model->row();
            }
        }

        return self::$publishedRows;
    }

    /**
     * Resets the cache of findPublishedRows().
     */
    public static function resetCache(): void
    {
        self::$publishedRows = null;
    }
}
