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
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

class ResourceBooking extends Backend
{
    #[AsCallback(table: 'tl_resource_booking', target: 'fields.moduleId.options')]
    public function getRbbModules(): array
    {
        $opt = [];
        $objDb = $this->Database
            ->prepare('SELECT * FROM tl_module WHERE type=?')
            ->execute(ResourceBookingWeekcalendarController::TYPE)
        ;

        while ($objDb->next()) {
            $opt[$objDb->id] = $objDb->name;
        }

        return $opt;
    }
}
