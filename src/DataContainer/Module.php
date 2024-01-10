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
use Contao\Database;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class Module
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_resourceTypes.options')]
    public function getResourceTypes(): array
    {
        $result = $this->connection->executeQuery('SELECT id,title FROM tl_resource_booking_resource_type');

        return $result->fetchAllKeyValue();
    }

    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_appConfig.options')]
    public function getAppConfigurations(): array
    {
        $appConfig = System::getContainer()->getParameter('markocupic_resource_booking.apps');

        return array_keys($appConfig);
    }

    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_clientPersonalData.options')]
    public function getTlMemberFields(): array
    {
        $arrFieldNames = Database::getInstance()->getFieldNames('tl_member');

        System::loadLanguageFile('tl_member');
        $arrOpt = [];

        foreach ($arrFieldNames as $fieldName) {
            if ('id' === $fieldName || 'password' === $fieldName) {
                continue;
            }
            $arrOpt[] = $fieldName;
        }

        unset($arrOpt['id'], $arrOpt['password']);

        return $arrOpt;
    }

    #[AsCallback(table: 'tl_module', target: 'fields.getTlResourceBookingFields.options')]
    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_bookingSubmittedFields.options')]
    public function getTlResourceBookingFields(): array
    {
        $arrFieldNames = Database::getInstance()->getFieldNames('tl_resource_booking');
        System::loadLanguageFile('tl_resource_booking');

        return $arrFieldNames;
    }
}
