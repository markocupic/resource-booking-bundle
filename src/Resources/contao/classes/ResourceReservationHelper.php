<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */

namespace Markocupic\ResourceReservationBundle;

use Contao\Database;
use Contao\Date;
use Contao\ResourceReservationModel;

/**
 * Class ResourceReservationHelper
 * @package Markocupic\ResourceReservationBundle
 */
class ResourceReservationHelper
{

    /**
     * @param $objRes
     * @param $slotStartTime
     * @param $slotEndTime
     * @return bool
     */
    public function isResourceBooked($objRes, $slotStartTime, $slotEndTime)
    {
        if(static::getBookedResourcesInSlot($objRes, $slotStartTime, $slotEndTime) === null){
            return false;
        }
        return true;
    }

    /**
     * @param $objRes
     * @param $slotStartTime
     * @param $slotEndTime
     * @return \Contao\Model\Collection|null
     */
    public function getBookedResourcesInSlot($objRes, $slotStartTime, $slotEndTime)
    {

        $arrIDS = array();
        // 1. possible case  -|---- | or -|-----| or -|-----|--
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_reservation WHERE (startTime<? AND endTime>?) AND pid=?')
            ->execute($slotStartTime, $slotStartTime, $objRes->id);
        if ($objDb->numRows)
        {
            while($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        // 2. possible case  |----- | or |-----| or |-----|--
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_reservation WHERE (startTime=? AND endTime>?) AND pid=?')
            ->execute($slotStartTime, $slotStartTime, $objRes->id);
        if ($objDb->numRows)
        {
            while($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        // 3. possible case  | --- | or -| ----| or | ----|--
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_reservation WHERE (startTime>? AND startTime<? AND endTime>?) AND pid=?')
            ->execute($slotStartTime, $slotEndTime, $slotEndTime, $objRes->id);
        if ($objDb->numRows)
        {
            while($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        // 4. possible case  |----|
        $objDb = Database::getInstance()->prepare('SELECT id FROM tl_resource_reservation WHERE (startTime=? AND endTime=?) AND pid=?')
            ->execute($slotStartTime, $slotEndTime, $objRes->id);
        if ($objDb->numRows)
        {
            while($objDb->next())
            {
                $arrIDS[] = $objDb->id;
            }
        }

        if (count($arrIDS) > 0)
        {
            $arrIDS = array_unique($arrIDS);
            return ResourceReservationModel::findMultipleByIds($arrIDS);
        }

        return null;
    }
}
