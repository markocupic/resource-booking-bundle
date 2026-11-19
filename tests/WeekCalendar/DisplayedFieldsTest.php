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

namespace Markocupic\ResourceBookingBundle\Tests\WeekCalendar;

use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\WeekCalendar\DisplayedFields;

class DisplayedFieldsTest extends ContaoTestCase
{
    public function testOnlyPermittedMemberFieldsAreDisplayed(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayClientPersonalData' => '1',
            // email is not permitted in the bundle configuration, password never
            'resourceBooking_clientPersonalData' => 'firstname,lastname,email,password',
        ]);

        $this->assertSame(['firstname', 'lastname'], (new DisplayedFields(['firstname', 'lastname', 'city', 'password']))->getMemberFields($module));
    }

    public function testNoMemberFieldsAreDisplayedIfDisabled(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayClientPersonalData' => '',
            'resourceBooking_clientPersonalData' => 'firstname,lastname',
        ]);

        $this->assertSame([], (new DisplayedFields())->getMemberFields($module));
    }

    public function testOnlyPermittedBookingFieldsAreDisplayed(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayBookingDetails' => '1',
            // member and bookingUuid are not permitted by default
            'resourceBooking_bookingDetailFields' => 'title,member,description,bookingUuid',
        ]);

        $this->assertSame(['title', 'description'], (new DisplayedFields())->getBookingFields($module));

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayBookingDetails' => '',
            'resourceBooking_bookingDetailFields' => 'title',
        ]);

        $this->assertSame([], (new DisplayedFields())->getBookingFields($module));
    }
}
