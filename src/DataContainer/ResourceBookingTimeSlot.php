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

namespace Markocupic\ResourceBookingBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ResourceBookingTimeSlot
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private RequestStack $requestStack,
    ) {
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'list.sorting.child_record')]
    public function childRecordCallback(array $row): string
    {
        $startTimeFormatted = $this->framework
            ->getAdapter(UtcTimeHelper::class)
            ->parseStartTime($row['startTime'])
        ;

        $endTimeFormatted = $this->framework
            ->getAdapter(UtcTimeHelper::class)
            ->parseEndTime($row['endTime'])
        ;

        $endTimeFormatted = '00:00' === $endTimeFormatted ? '24:00' : $endTimeFormatted;

        return \sprintf('<div class="tl_content_left"><span style="color:#999;padding-left:3px">'.$row['title'].'</span> %s-%s</div>', $startTimeFormatted, $endTimeFormatted);
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.startTime.load', priority: 100)]
    public function loadStartTime(int $timestamp): string
    {
        $strTime = '';

        if ($timestamp >= 0) {
            $strTime = $this->framework
                ->getAdapter(UtcTimeHelper::class)
                ->parseStartTime($timestamp)
            ;
        }

        return $strTime;
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.endTime.load', priority: 100)]
    public function loadEndTime(int $timestamp): string
    {
        $strTime = '';

        if ($timestamp >= 0) {
            $strTime = $this->framework
                ->getAdapter(UtcTimeHelper::class)
                ->parseEndTime($timestamp)
            ;
        }

        return $strTime;
    }

    /**
     * Converts formatted time f.ex 09:01 into an utc timestamp.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.startTime.save', priority: 100)]
    public function setCorrectStartTime(string $strTime, DataContainer $dc): int
    {
        $request = $this->requestStack->getCurrentRequest();

        if (preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $strTime)) {
            $timestamp = $this->framework
                ->getAdapter(UtcTimeHelper::class)
                ->strToTime('1970-01-01 '.$strTime)
            ;
        } else {
            $timestamp = $this->framework
                ->getAdapter(UtcTimeHelper::class)
                ->strToTime('1970-01-01 00:00')
            ; // -> 0

            $request->request->set('startTime', '00:00');
        }

        return $timestamp;
    }

    /**
     * Converts formatted time f.ex 09:01 into an utc timestamp.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'fields.endTime.save', priority: 100)]
    public function setCorrectEndTime(string $strTime, DataContainer $dc): int
    {
        $request = $this->requestStack->getCurrentRequest();

        if (preg_match('/^(24|2[0-3]|[01][0-9]):[0-5][0-9]$/', $strTime)) {
            if ('00:00' === $strTime) {
                $strTime = '24:00';
                $request->request->set('endTime', '24:00');
            }

            if ('24:00' === $strTime) {
                $timestampEndTime = $this->framework
                    ->getAdapter(UtcTimeHelper::class)
                    ->strToTime('1970-01-02 00:00')
                ;
            } else {
                $timestampEndTime = $this->framework
                    ->getAdapter(UtcTimeHelper::class)
                    ->strToTime('1970-01-01 '.$strTime)
                ;
            }
        } else {
            $timestampEndTime = 0;
        }

        // Adjust endTime if it is smaller than the startTime
        if (!empty($request->request->get('startTime'))) {
            $strStartTime = $request->request->get('startTime');
        } else {
            $strStartTime = $this->framework
                ->getAdapter(UtcTimeHelper::class)
                ->parseStartTime($dc->activeRecord->startTime)
            ;
        }

        if (!empty($strStartTime)) {
            $startTime = $this->framework
                ->getAdapter(UtcTimeHelper::class)
                ->strToTime('01-01-1970 '.$strStartTime)
            ;

            if ($timestampEndTime <= $startTime) {
                $timestampEndTime = $startTime + 60;
            }
        } else {
            $timestampEndTime = 0;
        }

        return $timestampEndTime;
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.ondelete')]
    public function removeChildRecords(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $arrIdsDel = $this->connection
            ->fetchFirstColumn(
                'SELECT id FROM tl_resource_booking WHERE timeSlotId = ?',
                [$dc->id],
                [Types::INTEGER],
            )
        ;

        if (empty($arrIdsDel)) {
            return;
        }

        // Delete child bookings
        $this->connection
            ->delete(
                'tl_resource_booking',
                ['timeSlotId' => $dc->id],
                [Types::INTEGER],
            )
        ;

        $this->framework
            ->getAdapter(Message::class)
            ->addInfo('Deleted bookings with ids '.implode(',', $arrIdsDel))
        ;
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.onsubmit')]
    public function adaptBookingStartAndEndTime(DataContainer $dc): void
    {
        $intId = $dc->id;

        if (!$intId) {
            return;
        }

        $arrSlot = $this->connection->fetchAssociative(
            'SELECT * FROM tl_resource_booking_time_slot WHERE id = ?',
            [$intId],
            [Types::INTEGER],
        );

        if (!$arrSlot) {
            return;
        }

        $arrAdapted = [];
        $arrBookings = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_resource_booking WHERE timeSlotId = ?',
            [$arrSlot['id']],
            [Types::INTEGER],
        );

        if (empty($arrBookings)) {
            return;
        }

        foreach ($arrBookings as $arrBooking) {
            $set = [];
            $arrFields = ['startTime', 'endTime'];

            foreach ($arrFields as $field) {
                $strDateOld = $this->framework
                    ->getAdapter(Date::class)
                    ->parse('Y-m-d H:i', $arrBooking[$field])
                ;

                $arrDateOld = explode(' ', $strDateOld);

                $strTimeNew = 'startTime' === $field
                    ?
                    $this->framework
                        ->getAdapter(UtcTimeHelper::class)
                        ->parseStartTime($arrSlot[$field])
                    :
                    $this->framework->getAdapter(UtcTimeHelper::class)
                        ->parseEndTime($arrSlot[$field])
                ;

                $strDateNew = $arrDateOld[0].' '.$strTimeNew;

                $set[$field] = strtotime($strDateNew);
            }

            $arrAdapted[] = $arrBooking['id'];

            $this->connection->update(
                'tl_resource_booking',
                $set,
                ['id' => $arrBooking['id']],
                [Types::INTEGER],
            );
        }

        if (!empty($arrAdapted)) {
            $this->framework
                ->getAdapter(Message::class)
                ->addInfo('Adapted start- and end-time for booking with ids '.implode(',', $arrAdapted))
            ;
        }
    }
}
