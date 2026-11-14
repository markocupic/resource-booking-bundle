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

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\HttpFoundation\RequestStack;

class UtilsTest extends ContaoTestCase
{
    private const APP_CONFIGS = [
        'default' => ['permitted_form_fields' => []],
        'phone_app' => ['permitted_form_fields' => ['phone', 'itemsBooked']],
    ];

    public function testGetAppConfigByNameReturnsTheSelectedApp(): void
    {
        $this->assertSame(
            [
                'autoSelectFirstResource' => true,
                // The base fields are always permitted, duplicates are removed.
                'permittedFormFields' => ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp', 'phone'],
            ],
            $this->getUtils()->getAppConfigByName('phone_app'),
        );
    }

    public function testGetAppConfigByNameFallsBackToTheDefaultApp(): void
    {
        $expected = ['autoSelectFirstResource' => true, 'permittedFormFields' => ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp']];

        $this->assertSame($expected, $this->getUtils()->getAppConfigByName('unknown'));
        $this->assertSame($expected, $this->getUtils()->getAppConfigByName(null));
    }

    public function testGetAppConfigByNameFallsBackToTheDefaultsWithoutApps(): void
    {
        $utils = new Utils($this->createContaoFrameworkMock(), new RequestStack(), 'rbb_bag', []);

        $this->assertSame(
            ['autoSelectFirstResource' => true, 'permittedFormFields' => ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp']],
            $utils->getAppConfigByName('default'),
        );
    }

    public function testAutoSelectFirstResourceCanBeDisabledPerApp(): void
    {
        $utils = new Utils($this->createContaoFrameworkMock(), new RequestStack(), 'rbb_bag', [
            'default' => [],
            'no_auto_select' => ['auto_select_first_resource' => false],
        ]);

        $this->assertTrue($utils->getAppConfigByName('default')['autoSelectFirstResource']);
        $this->assertFalse($utils->getAppConfigByName('no_auto_select')['autoSelectFirstResource']);
    }

    public function testGetAppConfigWithoutActiveResourceStartsTheWeekOnMonday(): void
    {
        $appConfig = $this->getUtils()->getAppConfig();

        $this->assertSame('monday', $appConfig['beginWeek']);
        $this->assertSame(['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp'], $appConfig['permittedFormFields']);
    }

    public function testGetBeginWeekReadsTheTimetableOfTheResource(): void
    {
        $timeSlotType = $this->createClassWithPropertiesStub(ResourceBookingTimeSlotTypeModel::class, ['id' => 4, 'beginWeek' => 'sunday']);

        $adapter = $this->createAdapterMock(['findById']);
        $adapter
            ->expects($this->once())
            ->method('findById')
            ->with(4)
            ->willReturn($timeSlotType)
        ;

        $utils = new Utils(
            $this->createContaoFrameworkMock([ResourceBookingTimeSlotTypeModel::class => $adapter]),
            new RequestStack(),
            'rbb_bag',
            self::APP_CONFIGS,
        );

        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'timeSlotType' => 4]);

        $this->assertSame('sunday', $utils->getBeginWeek($resource));
    }

    public function testGetBeginWeekFallsBackToMonday(): void
    {
        $adapter = $this->createAdapterMock(['findById']);
        $adapter
            ->method('findById')
            ->willReturn(null)
        ;

        $utils = new Utils(
            $this->createContaoFrameworkMock([ResourceBookingTimeSlotTypeModel::class => $adapter]),
            new RequestStack(),
            'rbb_bag',
            self::APP_CONFIGS,
        );

        // Resource without a timetable
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'timeSlotType' => 0]);
        $this->assertSame('monday', $utils->getBeginWeek($resource));

        // Timetable not found
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'timeSlotType' => 4]);
        $this->assertSame('monday', $utils->getBeginWeek($resource));
    }

    public function testGetDisplayedMemberFieldsOnlyReturnsPermittedFields(): void
    {
        $utils = new Utils($this->frameworkWithDeserialize(), new RequestStack(), 'rbb_bag', self::APP_CONFIGS, ['firstname', 'lastname', 'city'], ['description']);

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayClientPersonalData' => '1',
            // email is not permitted in the bundle configuration, password never
            'resourceBooking_clientPersonalData' => serialize(['firstname', 'lastname', 'email', 'password']),
        ]);

        $this->assertSame(['firstname', 'lastname'], $utils->getDisplayedMemberFields($module));
    }

    public function testGetDisplayedMemberFieldsIsEmptyIfDisabled(): void
    {
        $utils = new Utils($this->frameworkWithDeserialize(), new RequestStack(), 'rbb_bag', self::APP_CONFIGS);

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayClientPersonalData' => '',
            'resourceBooking_clientPersonalData' => serialize(['firstname', 'lastname']),
        ]);

        $this->assertSame([], $utils->getDisplayedMemberFields($module));
    }

    public function testGetDisplayedBookingFieldsOnlyReturnsPermittedFields(): void
    {
        $utils = new Utils($this->frameworkWithDeserialize(), new RequestStack(), 'rbb_bag', self::APP_CONFIGS);

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayBookingDetails' => '1',
            // member and bookingUuid are not permitted by default
            'resourceBooking_bookingDetailFields' => serialize(['title', 'member', 'description', 'bookingUuid']),
        ]);

        $this->assertSame(['title', 'description'], $utils->getDisplayedBookingFields($module));

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, [
            'resourceBooking_displayBookingDetails' => '',
            'resourceBooking_bookingDetailFields' => serialize(['title']),
        ]);

        $this->assertSame([], $utils->getDisplayedBookingFields($module));
    }

    private function frameworkWithDeserialize(): \Contao\CoreBundle\Framework\ContaoFramework
    {
        $stringUtil = $this->createAdapterMock(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturnCallback(static fn (mixed $value, bool $forceArray = false): array => StringUtil::deserialize($value, $forceArray))
        ;

        return $this->createContaoFrameworkMock([StringUtil::class => $stringUtil]);
    }

    private function getUtils(): Utils
    {
        return new Utils($this->createContaoFrameworkMock(), new RequestStack(), 'rbb_bag', self::APP_CONFIGS);
    }
}
