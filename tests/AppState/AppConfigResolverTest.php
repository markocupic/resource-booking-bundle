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

namespace Markocupic\ResourceBookingBundle\Tests\AppState;

use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppConfigResolver;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;

class AppConfigResolverTest extends ContaoTestCase
{
    private const APP_CONFIGS = [
        'default' => ['permitted_form_fields' => []],
        'phone_app' => ['permitted_form_fields' => ['phone', 'itemsBooked']],
    ];

    public function testReturnsTheSelectedApp(): void
    {
        $appConfig = $this->getResolver()->getByName('phone_app');

        $this->assertSame('phone_app', $appConfig->name);
        $this->assertTrue($appConfig->autoSelectFirstResource);
        // Only the custom fields: the fields of the bundle are always permitted
        $this->assertSame(['phone', 'itemsBooked'], $appConfig->permittedFormFields);
    }

    public function testFallsBackToTheDefaultApp(): void
    {
        $this->assertEquals(new AppConfig('default'), $this->getResolver()->getByName('unknown'));
        $this->assertEquals(new AppConfig('default'), $this->getResolver()->getByName(null));
    }

    public function testFallsBackToTheDefaultsWithoutApps(): void
    {
        $resolver = new AppConfigResolver($this->createContaoFrameworkStub(), []);

        $this->assertEquals(new AppConfig('default', true, []), $resolver->getByName('default'));
    }

    public function testAutoSelectFirstResourceCanBeDisabledPerApp(): void
    {
        $resolver = new AppConfigResolver($this->createContaoFrameworkStub(), [
            'default' => [],
            'no_auto_select' => ['auto_select_first_resource' => false],
        ]);

        $this->assertTrue($resolver->getByName('default')->autoSelectFirstResource);
        $this->assertFalse($resolver->getByName('no_auto_select')->autoSelectFirstResource);
    }

    public function testReturnsTheAppConfigurationSelectedInTheModule(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['resourceBooking_appConfig' => 'phone_app']);

        $this->assertSame('phone_app', $this->getResolver()->getAppConfig($module)->name);

        // Nothing selected
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['resourceBooking_appConfig' => '']);

        $this->assertSame('default', $this->getResolver()->getAppConfig($module)->name);
        $this->assertSame('default', $this->getResolver()->getAppConfig(null)->name);
    }

    public function testWithoutActiveResourceTheWeekStartsOnMonday(): void
    {
        $this->assertSame('monday', $this->getResolver()->getBeginWeek(null));
    }

    public function testReadsTheFirstDayOfTheWeekFromTheTimetableOfTheResource(): void
    {
        $timetable = $this->createClassWithPropertiesStub(ResourceBookingTimeSlotTypeModel::class, ['id' => 4, 'beginWeek' => 'sunday']);

        $adapter = $this->createAdapterMock(['findById']);
        $adapter
            ->expects($this->once())
            ->method('findById')
            ->with(4)
            ->willReturn($timetable)
        ;

        $resolver = new AppConfigResolver($this->createContaoFrameworkStub([ResourceBookingTimeSlotTypeModel::class => $adapter]), self::APP_CONFIGS);
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'timeSlotType' => 4]);

        $this->assertSame('sunday', $resolver->getBeginWeek($resource));
    }

    public function testTheWeekStartsOnMondayWithoutTimetable(): void
    {
        $adapter = $this->createAdapterStub(['findById']);
        $adapter
            ->method('findById')
            ->willReturn(null)
        ;

        $resolver = new AppConfigResolver($this->createContaoFrameworkStub([ResourceBookingTimeSlotTypeModel::class => $adapter]), self::APP_CONFIGS);

        // Resource without a timetable
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'timeSlotType' => 0]);
        $this->assertSame('monday', $resolver->getBeginWeek($resource));

        // Timetable not found
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'timeSlotType' => 4]);
        $this->assertSame('monday', $resolver->getBeginWeek($resource));
    }

    private function getResolver(): AppConfigResolver
    {
        return new AppConfigResolver($this->createContaoFrameworkStub(), self::APP_CONFIGS);
    }
}
