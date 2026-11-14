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
use Contao\Database;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Module
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        #[Autowire('%markocupic_resource_booking.apps%')]
        private readonly array $appConfigs = [],
        #[Autowire('%markocupic_resource_booking.displayable_member_fields%')]
        private readonly array $displayableMemberFields = RbbConfig::RBB_DEFAULT_DISPLAYABLE_MEMBER_FIELDS,
        #[Autowire('%markocupic_resource_booking.displayable_booking_fields%')]
        private readonly array $displayableBookingFields = RbbConfig::RBB_DEFAULT_DISPLAYABLE_BOOKING_FIELDS,
    ) {
    }

    /**
     * @return list<string>
     */
    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_appConfig.options')]
    public function getAppConfigurations(): array
    {
        return array_map('strval', array_keys($this->appConfigs));
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

    /**
     * Only the fields configured in markocupic_resource_booking.displayable_member_fields
     * can be selected (the data is visible to all logged-in members).
     *
     * @return list<string>
     */
    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_clientPersonalData.options')]
    public function getTlMemberFields(): array
    {
        $fields = array_diff($this->displayableMemberFields, RbbConfig::RBB_FORBIDDEN_MEMBER_FIELDS);

        return $this->getExistingFields('tl_member', $fields);
    }

    /**
     * Only the fields configured in markocupic_resource_booking.displayable_booking_fields
     * can be selected (the data is visible to all logged-in members).
     *
     * @return list<string>
     */
    #[AsCallback(table: 'tl_module', target: 'fields.resourceBooking_bookingDetailFields.options')]
    public function getTlResourceBookingFields(): array
    {
        return $this->getExistingFields('tl_resource_booking', $this->displayableBookingFields);
    }

    /**
     * Returns the given fields that exist in the table (in the configured order)
     * and loads the language file for the labels.
     *
     * @param array<string> $fields
     *
     * @return list<string>
     */
    private function getExistingFields(string $table, array $fields): array
    {
        $fieldNames = $this->framework
            ->getAdapter(Database::class)
            ->getInstance()->getFieldNames($table)
        ;

        $this->framework
            ->getAdapter(System::class)
            ->loadLanguageFile($table)
        ;

        return array_values(array_filter($fields, static fn (string $field): bool => \in_array($field, $fieldNames, true)));
    }
}
