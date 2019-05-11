<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */

namespace Contao;

/**
 * Class ResourceBookingResourceTypeModel
 * @package Contao
 */
class ResourceBookingResourceTypeModel extends \Model
{

	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_resource_booking_resource_type';

    /**
     * @param $arrIds
     * @return Model\Collection|null
     */
    public static function findMultipleAndPublishedByIds($arrIds)
    {
        $newArrIds = array();
        $objResTypes = static::findMultipleByIds($arrIds);
        if ($objResTypes !== null)
        {
            while ($objResTypes->next())
            {
                if ($objResTypes->published)
                {
                    $newArrIds[] = $objResTypes->id;
                }
            }
        }

        return static::findMultipleByIds($newArrIds);
    }

}
