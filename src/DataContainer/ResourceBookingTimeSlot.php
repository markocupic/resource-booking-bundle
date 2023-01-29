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

namespace Markocupic\ResourceBookingBundle\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Input;
use Contao\Message;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Contao\Database;

class ResourceBookingTimeSlot
{
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'list.sorting.child_record')]
    public function childRecordCallback(array $row): string
    {
        return sprintf('<div class="tl_content_left"><span style="color:#999;padding-left:3px">'.$row['title'].'</span> %s-%s</div>', UtcTimeHelper::parse('H:i', $row['startTime']), UtcTimeHelper::parse('H:i', $row['endTime']));
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.startTime.load', priority: 100)]
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.endTime.load')]
    public function loadTime(int $timestamp): string
    {
        $strTime = '';

        if ($timestamp >= 0) {
            $strTime = UtcTimeHelper::parse('H:i', $timestamp);
        }

        return $strTime;
    }

    /**
     * Converts formatted time f.ex 09:01 into an utc timestamp.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.startTime.save')]
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.endTime.save')]
    public function setCorrectTime(string $strTime, DataContainer $dc): int
    {
        if (preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $strTime)) {
            $timestamp = UtcTimeHelper::strToTime('1970-01-01 '.$strTime);
        } else {
            $timestamp = 0;
        }

        return $timestamp;
    }

    /**
     * Adjust endTime if it is smaller than the startTime.
     *
     * @param int           $timestamp
     * @param DataContainer $dc
     *
     * @throws \Exception
     *
     * @return int
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.endTime.save')]
    public function setCorrectEndTime(int $timestamp, DataContainer $dc): int
    {
        // Adjust endTime if it is smaller than the startTime
        if (!empty(Input::post('startTime'))) {
            $strStartTime = Input::post('startTime');
        } else {
            $strStartTime = $dc->activeRecord->startTime;
        }

        if (!empty($strStartTime)) {
            $startTime = UtcTimeHelper::strToTime('01-01-1970 '.$strStartTime);

            if ($timestamp <= $startTime) {
                $timestamp = $startTime + 60;
            }
        } else {
            $timestamp = 0;
        }

        return $timestamp;
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.ondelete')]
    public function removeChildRecords(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objBooking = Database::getInstance()->prepare('SELECT id FROM tl_resource_booking WHERE timeSlotId=?')->execute($dc->id);

        if ($objBooking->numRows) {
            $arrIdsDel = $objBooking->fetchEach('id');
            // Delete child bookings
            Database::getInstance()->prepare('DELETE FROM tl_resource_booking WHERE timeSlotId=?')->execute($dc->id);
            Message::addInfo('Deleted bookings with ids '.implode(',', $arrIdsDel));
        }
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.onsubmit')]
    public function adaptBookingStartAndEndTime(DataContainer $dc): void
    {
        $intId = $dc->id;

        if (!$intId) {
            return;
        }

        $objSlot = Database::getInstance()->prepare('SELECT * FROM tl_resource_booking_time_slot WHERE id=?')->execute($intId);

        if ($objSlot->numRows) {
            $arrAdapted = [];
            $objBooking = Database::getInstance()->prepare('SELECT * FROM tl_resource_booking WHERE timeSlotId=?')->execute($objSlot->id);

            while ($objBooking->next()) {
                $set = [];
                $arrFields = ['startTime', 'endTime'];

                foreach ($arrFields as $field) {
                    $strDateOld = Date::parse('Y-m-d H:i', $objBooking->{$field});
                    $arrDateOld = explode(' ', $strDateOld);
                    $strTimeNew = UtcTimeHelper::parse('H:i', $objSlot->{$field});
                    $strDateNew = $arrDateOld[0].' '.$strTimeNew;
                    $set[$field] = strtotime($strDateNew);
                }
                $arrAdapted[] = $objBooking->id;
                Database::getInstance()->prepare('UPDATE tl_resource_booking %s WHERE id=?')
                    ->set($set)
                    ->execute($objBooking->id)
                ;
            }

            if (\count($arrAdapted)) {
                Message::addInfo('Adapted start- and endtime for booking with ids '.implode(',', $arrAdapted));
            }
        }
    }
}
