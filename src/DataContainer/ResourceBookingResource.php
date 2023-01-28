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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

class ResourceBookingResource
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_resource', target: 'list.sorting.child_record')]
    public function getRbbModules(): array
    {
        $result = $this->connection->executeQuery('SELECT id,name FROM tl_module WHERE type = ?', [ResourceBookingWeekcalendarController::TYPE]);

        return $result->fetchAllKeyValue();
    }
}