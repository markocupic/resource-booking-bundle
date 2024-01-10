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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Member
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_member', target: 'config.ondelete')]
    public function deleteChildRecords(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        // Delete child bookings
        $this->connection->delete('tl_resource_booking', ['member' => $dc->id]);
    }
}
