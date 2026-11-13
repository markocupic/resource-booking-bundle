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
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingTimeSlotType
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * A booking timetable whose time slots have future bookings must not be deleted:
     * the deletion is aborted and an error message with the number of affected
     * bookings is shown. Otherwise Contao deletes the time slots (ctable) and the
     * past bookings are kept, so the booking history is not lost.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot_type', target: 'config.ondelete')]
    public function preventDeletionWithFutureBookings(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_resource_booking
                WHERE endTime > ? AND timeSlotId IN (
                    SELECT id FROM tl_resource_booking_time_slot WHERE pid = ?
                )',
            [time(), (int) $dc->id],
            [Types::INTEGER, Types::INTEGER],
        );

        if ($count < 1) {
            return;
        }

        $this->framework
            ->getAdapter(Message::class)
            ->addError($this->translator->trans('RBB.BE.deleteTimeSlotTypeBlocked', [$count], 'contao_default'))
        ;

        throw new RedirectResponseException($this->framework->getAdapter(System::class)->getReferer());
    }
}
