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

namespace Markocupic\ResourceBookingBundle\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class ResourceBookingTimeSlotType
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[AsCallback(table: 'tl_resource_booking_time_slot_type', target: 'list.operations.editheader.button')]
    public function editHeader(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        return '<a href="'.Backend::addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot_type', target: 'config.ondelete')]
    public function removeChildRecords(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }
        // Delete child bookings
        $this->connection->executeStatement('DELETE FROM tl_resource_booking WHERE tl_resource_booking.timeSlotId IN (SELECT id FROM tl_resource_booking_time_slot WHERE tl_resource_booking_time_slot.pid = ?)', [$dc->id]);

        // Delete time slot children
        $this->connection->executeStatement('DELETE FROM tl_resource_booking_time_slot WHERE pid = ?', [$dc->id]);
    }
}
