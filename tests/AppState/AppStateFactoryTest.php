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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\HttpFoundation\Request;

class AppStateFactoryTest extends ContaoTestCase
{
    private const APP_CONFIG = ['beginWeek' => 'monday', 'autoSelectFirstResource' => true, 'permittedFormFields' => []];

    /**
     * @var array<int, ResourceBookingResourceTypeModel>
     */
    private array $types = [];

    /**
     * @var array<int, ResourceBookingResourceModel>
     */
    private array $resources = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Module with the resource types 7 and 8. Type 7 has the resources 9 and 10, type 8 the resource 11.
        // Type 12 and resource 13 exist, but do not belong to the module.
        $this->types = [
            7 => $this->createClassWithPropertiesStub(ResourceBookingResourceTypeModel::class, ['id' => 7]),
            8 => $this->createClassWithPropertiesStub(ResourceBookingResourceTypeModel::class, ['id' => 8]),
            12 => $this->createClassWithPropertiesStub(ResourceBookingResourceTypeModel::class, ['id' => 12]),
        ];

        $this->resources = [
            9 => $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 9, 'pid' => 7]),
            10 => $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 10, 'pid' => 7]),
            11 => $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 11, 'pid' => 8]),
            13 => $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 13, 'pid' => 12]),
        ];
    }

    public function testADirectLinkToAResourceSelectsItsResourceType(): void
    {
        [$type, $resource] = $this->getFactory()->resolveSelection($this->getModule(), 0, 11, false);

        $this->assertSame($this->types[8], $type);
        $this->assertSame($this->resources[11], $resource);
    }

    public function testTheResourceIsResetIfAnotherResourceTypeHasBeenSelected(): void
    {
        [$type, $resource] = $this->getFactory()->resolveSelection($this->getModule(), 8, 9, false);

        $this->assertSame($this->types[8], $type);
        $this->assertNull($resource);
    }

    public function testResourcesAndResourceTypesOfOtherModulesAreIgnored(): void
    {
        $this->assertSame([null, null], $this->getFactory()->resolveSelection($this->getModule(), 0, 13, false));
        $this->assertSame([null, null], $this->getFactory()->resolveSelection($this->getModule(), 12, 0, false));
    }

    public function testUnknownIdsAreIgnored(): void
    {
        $this->assertSame([null, null], $this->getFactory()->resolveSelection($this->getModule(), 99, 98, false));
    }

    public function testNothingIsSelectedAutomaticallyAfterTheFirstRequest(): void
    {
        $this->assertSame([null, null], $this->getFactory()->resolveSelection($this->getModule(), 0, 0, false));
    }

    public function testTheFirstAvailableResourceIsSelectedOnTheFirstRequest(): void
    {
        [$type, $resource] = $this->getFactory()->resolveSelection($this->getModule(), 0, 0, true);

        $this->assertSame($this->types[7], $type);
        $this->assertSame($this->resources[9], $resource);
    }

    public function testASelectedResourceTypeIsKeptOnTheFirstRequest(): void
    {
        // The only resource of type 8 is selected
        [$type, $resource] = $this->getFactory()->resolveSelection($this->getModule(), 8, 0, true);

        $this->assertSame($this->types[8], $type);
        $this->assertSame($this->resources[11], $resource);

        // Type 7 has two resources: nothing is selected
        [$type, $resource] = $this->getFactory()->resolveSelection($this->getModule(), 7, 0, true);

        $this->assertSame($this->types[7], $type);
        $this->assertNull($resource);
    }

    public function testTheSingleResourceTypeIsSelectedIfTheAutoSelectionIsDisabled(): void
    {
        $factory = $this->getFactory(['default' => ['autoSelectFirstResource' => false]]);

        // Two resource types: nothing is selected
        $this->assertSame([null, null], $factory->resolveSelection($this->getModule([7, 8]), 0, 0, true));

        // Only resource type 8: its only resource is selected
        [$type, $resource] = $factory->resolveSelection($this->getModule([8]), 0, 0, true);

        $this->assertSame($this->types[8], $type);
        $this->assertSame($this->resources[11], $resource);
    }

    public function testTheCurrentWeekIsDisplayedWithoutADate(): void
    {
        $this->assertSame([1000, 500, 2000], $this->getFactory()->resolveWeeks(self::APP_CONFIG, null, 0, 1000));
    }

    public function testTheDateIsMovedToTheFirstDayOfItsWeek(): void
    {
        // getFirstDayOfWeek() of the mocked DateHelper subtracts 5
        $this->assertSame([1495, 500, 2000], $this->getFactory()->resolveWeeks(self::APP_CONFIG, null, 1500, 1000));
    }

    public function testTheDisplayedWeekIsClampedToThePermittedWeeks(): void
    {
        $this->assertSame([500, 500, 2000], $this->getFactory()->resolveWeeks(self::APP_CONFIG, null, 105, 1000));
        $this->assertSame([2000, 500, 2000], $this->getFactory()->resolveWeeks(self::APP_CONFIG, null, 9005, 1000));
    }

    public function testCreateBuildsTheAppState(): void
    {
        $module = $this->getModule();
        $page = $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]);

        $state = $this->getFactory()->create('33_1', $module, $page, ['resType' => '7', 'res' => '10', 'date' => '1500'], false, 'de_CH', 1000);

        $this->assertSame('33_1', $state->moduleKey);
        $this->assertSame($module, $state->module);
        $this->assertSame($page, $state->page);
        $this->assertSame($this->types[7], $state->resourceType);
        $this->assertSame($this->resources[10], $state->resource);
        $this->assertSame(self::APP_CONFIG, $state->appConfig);
        $this->assertSame(1495, $state->activeWeekTstamp);
        $this->assertSame(500, $state->firstPermittedWeekTstamp);
        $this->assertSame(2000, $state->lastPermittedWeekTstamp);
        $this->assertSame('de-CH', $state->language);
        $this->assertSame(10, $state->getResourceId());
    }

    public function testInvalidInputIsIgnored(): void
    {
        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['resType' => 'undefined', 'res' => '9 OR 1=1', 'date' => '-5'], false, 'de', 1000);

        $this->assertNull($state->resourceType);
        $this->assertNull($state->resource);
        $this->assertSame(1000, $state->activeWeekTstamp);
    }

    public function testADirectLinkMayContainADate(): void
    {
        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['date' => '2026-11-20'], false, 'de', 1000);

        // Clamped to the last permitted week of the mocked WeekBoundaries
        $this->assertSame(2000, $state->activeWeekTstamp);

        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['date' => '2026-02-31'], false, 'de', 1000);

        // Invalid date: current week
        $this->assertSame(1000, $state->activeWeekTstamp);
    }

    public function testTheAppStateTravelsWithTheRequest(): void
    {
        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), [], false, 'de', 1000);
        $request = new Request();
        $state->attachTo($request);

        $this->assertSame($state, AppState::fromRequest($request));

        $this->expectException(\LogicException::class);

        AppState::fromRequest(new Request());
    }

    /**
     * @param array<int> $resourceTypeIds
     */
    private function getModule(array $resourceTypeIds = [7, 8]): ModuleModel
    {
        return $this->createClassWithPropertiesStub(ModuleModel::class, [
            'id' => 33,
            'resourceBooking_resourceTypes' => serialize(array_map('strval', $resourceTypeIds)),
            'resourceBooking_appConfig' => 'default',
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $appConfigs
     */
    private function getFactory(array $appConfigs = ['default' => ['autoSelectFirstResource' => true]]): AppStateFactory
    {
        $utils = $this->createStub(Utils::class);
        $utils
            ->method('getAppConfig')
            ->willReturn(self::APP_CONFIG)
        ;

        $utils
            ->method('getAppConfigByName')
            ->willReturnCallback(static fn (string|null $name): array => $appConfigs[$name ?? 'default'])
        ;

        $weekBoundaries = $this->createStub(WeekBoundaries::class);
        $weekBoundaries
            ->method('getFirstPermittedWeek')
            ->willReturn(500)
        ;

        $weekBoundaries
            ->method('getLastPermittedWeek')
            ->willReturn(2000)
        ;

        return new AppStateFactory($this->getFramework(), $utils, $weekBoundaries);
    }

    private function getFramework(): ContaoFramework
    {
        $types = $this->types;
        $resources = $this->resources;

        $typeAdapter = $this->createAdapterStub(['findPublishedByPk', 'findPublishedByIds']);
        $typeAdapter
            ->method('findPublishedByPk')
            ->willReturnCallback(static fn (int $id) => $types[$id] ?? null)
        ;
        $typeAdapter
            ->method('findPublishedByIds')
            ->willReturnCallback(
                static function (array $ids) use ($types): Collection|null {
                    $models = array_values(array_filter(array_map(static fn ($id) => $types[(int) $id] ?? null, $ids)));

                    return $models ? new Collection($models, 'tl_resource_booking_resource_type') : null;
                },
            )
        ;

        $resourceAdapter = $this->createAdapterStub(['findPublishedByPk', 'findPublishedByPid']);
        $resourceAdapter
            ->method('findPublishedByPk')
            ->willReturnCallback(static fn (int $id) => $resources[$id] ?? null)
        ;
        $resourceAdapter
            ->method('findPublishedByPid')
            ->willReturnCallback(
                static function (int $pid) use ($resources): Collection|null {
                    $models = array_values(array_filter($resources, static fn ($resource): bool => (int) $resource->pid === $pid));

                    return $models ? new Collection($models, 'tl_resource_booking_resource') : null;
                },
            )
        ;

        $dateHelperAdapter = $this->createAdapterStub(['getFirstDayOfWeek', 'getFirstDayOfCurrentWeek']);
        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturnCallback(static fn (array $appConfig, int $tstamp): int => $tstamp - 5)
        ;

        $dateHelperAdapter
            ->method('getFirstDayOfCurrentWeek')
            ->willReturn(1000)
        ;

        return $this->createContaoFrameworkStub([
            ResourceBookingResourceTypeModel::class => $typeAdapter,
            ResourceBookingResourceModel::class => $resourceAdapter,
            DateHelper::class => $dateHelperAdapter,
        ]);
    }
}
