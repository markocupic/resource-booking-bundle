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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

class ResourceBooking
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[AsCallback(table: 'tl_resource_booking', target: 'fields.moduleId.options')]
    public function getRbbModules(): array
    {
        $opt = [];

        $modules = $this->connection
            ->fetchAllAssociative(
                'SELECT * FROM tl_module WHERE type = ?',
                [ResourceBookingWeekcalendarController::TYPE],
                [Types::STRING],
            );

        foreach ($modules as $module) {
            $opt[$module['id']] = $module['name'];
        }

        return $opt;
    }
}
