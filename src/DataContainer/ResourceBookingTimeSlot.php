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

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ResourceBookingTimeSlot
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
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

    /**
     * A time slot with future bookings must not be deleted: the deletion is
     * aborted and an error message with the number of affected bookings is shown.
     * Past bookings are kept, so the booking history is not lost.
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.ondelete')]
    public function preventDeletionWithFutureBookings(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_resource_booking WHERE timeSlotId = ? AND endTime > ?',
            [(int) $dc->id, time()],
            [Types::INTEGER, Types::INTEGER],
        );

        if ($count < 1) {
            return;
        }

        $this->framework
            ->getAdapter(Message::class)
            ->addError($this->translator->trans('RBB.BE.deleteTimeSlotBlocked', [$count], 'contao_default'))
        ;

        throw new RedirectResponseException($this->framework->getAdapter(System::class)->getReferer());
    }

    /**
     * Adapts the start and end time of the future bookings (not started yet)
     * of a time slot after its times have been changed. Past bookings are not touched.
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.onsubmit')]
    public function adaptBookingStartAndEndTime(DataContainer $dc): void
    {
        $intId = (int) $dc->id;

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

        $arrBookings = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_resource_booking WHERE timeSlotId = ? AND startTime > ?',
            [$intId, time()],
            [Types::INTEGER, Types::INTEGER],
        );

        if (empty($arrBookings)) {
            return;
        }

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $utcTimeHelperAdapter = $this->framework->getAdapter(UtcTimeHelper::class);
        $datimFormat = (string) $this->framework->getAdapter(Config::class)->get('datimFormat');

        $newTimes = [
            'startTime' => $utcTimeHelperAdapter->parseStartTime((int) $arrSlot['startTime']),
            'endTime' => $utcTimeHelperAdapter->parseEndTime((int) $arrSlot['endTime']),
        ];

        $adapted = 0;

        foreach ($arrBookings as $arrBooking) {
            $set = [];

            foreach ($newTimes as $field => $strTimeNew) {
                // Keep the date of the booking, take the new time of the time slot
                $strDate = explode(' ', $dateAdapter->parse('Y-m-d H:i', (int) $arrBooking[$field]))[0];
                $set[$field] = strtotime($strDate.' '.$strTimeNew);
            }

            if ((int) $set['startTime'] === (int) $arrBooking['startTime'] && (int) $set['endTime'] === (int) $arrBooking['endTime']) {
                continue;
            }

            // The title ends with the booked time span, e.g. "Room : booked by John Doe [16.11.2026 08:00 - 16.11.2026 08:45]"
            $title = (string) ($arrBooking['title'] ?? '');

            if ('' !== $title && preg_match('/\[[^\]]*\]\s*$/', $title)) {
                $set['title'] = preg_replace(
                    '/\[[^\]]*\]\s*$/',
                    \sprintf('[%s - %s]', $dateAdapter->parse($datimFormat, $set['startTime']), $dateAdapter->parse($datimFormat, $set['endTime'])),
                    $title,
                );
            }

            $this->connection->update(
                'tl_resource_booking',
                $set,
                ['id' => $arrBooking['id']],
            );

            ++$adapted;
        }

        if ($adapted > 0) {
            $this->framework
                ->getAdapter(Message::class)
                ->addInfo($this->translator->trans('RBB.BE.bookingsAdapted', [$adapted], 'contao_default'))
            ;
        }
    }
}
