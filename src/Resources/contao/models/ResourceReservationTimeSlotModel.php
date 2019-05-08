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
 * Class ResourceReservationTimeSlotModel
 * @package Contao
 */
class ResourceReservationTimeSlotModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_resource_reservation_time_slot';

    /**
     * @param $intId
     * @return Model\Collection|null
     */
    public static function findPublishedByPid($intId)
    {
        $arrIds = array();
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_resource_reservation_time_slot WHERE pid=? AND published=? ORDER BY sorting')->execute($intId, '1');
        while($objDb->next())
        {
            $arrIds[] = $objDb->id;
        }
        return static::findMultipleByIds($arrIds);
    }

}
