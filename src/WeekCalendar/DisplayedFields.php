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

namespace Markocupic\ResourceBookingBundle\WeekCalendar;

use Contao\ModuleModel;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Util\CsvList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The fields of the member and of the booking that are displayed in the week calendar:
 * selected in the module and permitted in the bundle configuration
 * (markocupic_resource_booking.displayable_member_fields/displayable_booking_fields).
 * Only these fields may be sent to the front end (see BookingPresenter).
 */
class DisplayedFields
{
    /**
     * @param list<string> $displayableMemberFields
     * @param list<string> $displayableBookingFields
     */
    public function __construct(
        #[Autowire('%markocupic_resource_booking.displayable_member_fields%')]
        private readonly array $displayableMemberFields = RbbConfig::RBB_DEFAULT_DISPLAYABLE_MEMBER_FIELDS,
        #[Autowire('%markocupic_resource_booking.displayable_booking_fields%')]
        private readonly array $displayableBookingFields = RbbConfig::RBB_DEFAULT_DISPLAYABLE_BOOKING_FIELDS,
    ) {
    }

    /**
     * Fields of tl_member (tl_module.resourceBooking_clientPersonalData); some fields
     * are never sent (RbbConfig::RBB_FORBIDDEN_MEMBER_FIELDS).
     *
     * @return list<string>
     */
    public function getMemberFields(ModuleModel $module): array
    {
        if (!$module->resourceBooking_displayClientPersonalData) {
            return [];
        }

        $permitted = array_diff($this->displayableMemberFields, RbbConfig::RBB_FORBIDDEN_MEMBER_FIELDS);

        return array_values(array_intersect(CsvList::parse($module->resourceBooking_clientPersonalData), $permitted));
    }

    /**
     * Fields of tl_resource_booking (tl_module.resourceBooking_bookingDetailFields), in addition
     * to RbbConfig::RBB_ALWAYS_SENT_BOOKING_FIELDS.
     *
     * @return list<string>
     */
    public function getBookingFields(ModuleModel $module): array
    {
        if (!$module->resourceBooking_displayBookingDetails) {
            return [];
        }

        return array_values(array_intersect(CsvList::parse($module->resourceBooking_bookingDetailFields), $this->displayableBookingFields));
    }
}
