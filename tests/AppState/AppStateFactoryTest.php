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
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppConfigResolver;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\HttpFoundation\Request;

class AppStateFactoryTest extends ContaoTestCase
{
    // Wednesday
    private const NOW = '2026-11-04 12:00';

    private const FIRST_WEEK = '2026-10-05 00:00';

    private const LAST_WEEK = '2026-12-28 00:00';

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
        $factory = $this->getFactory(autoSelectFirstResource: false);

        // Two resource types: nothing is selected
        $this->assertSame([null, null], $factory->resolveSelection($this->getModule([7, 8]), 0, 0, true));

        // Only resource type 8: its only resource is selected
        [$type, $resource] = $factory->resolveSelection($this->getModule([8]), 0, 0, true);

        $this->assertSame($this->types[8], $type);
        $this->assertSame($this->resources[11], $resource);
    }

    public function testTheCurrentWeekIsDisplayedWithoutADate(): void
    {
        $now = strtotime('2030-10-16 12:00');
        [$activeWeek] = $this->getWeekFactory()->resolveWeeks('monday', null, 0, $now);

        $this->assertSame(strtotime('2030-10-14 00:00'), $activeWeek);
    }

    public function testTheDateIsMovedToTheFirstDayOfItsWeek(): void
    {
        $now = strtotime('2030-10-16 12:00');
        $factory = $this->getWeekFactory();

        $this->assertSame(strtotime('2030-10-21 00:00'), $factory->resolveWeeks('monday', null, strtotime('2030-10-25 18:00'), $now)[0]);

        // The timetable starts the week on sunday
        $this->assertSame(strtotime('2030-10-20 00:00'), $factory->resolveWeeks('sunday', null, strtotime('2030-10-25 18:00'), $now)[0]);
    }

    public function testTheDisplayedWeekIsClampedToThePermittedWeeks(): void
    {
        $now = strtotime('2030-10-16 12:00');
        $first = strtotime('2030-10-07 00:00');
        $last = strtotime('2030-12-30 00:00');
        $factory = $this->getWeekFactory($first, $last);

        $this->assertSame([$first, $first, $last], $factory->resolveWeeks('monday', null, strtotime('2029-01-01'), $now));
        $this->assertSame([$last, $first, $last], $factory->resolveWeeks('monday', null, strtotime('2035-01-01'), $now));
    }

    public function testCreateBuildsTheAppState(): void
    {
        $module = $this->getModule();
        $page = $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]);

        $state = $this->getFactory()->create('33_1', $module, $page, ['resType' => '7', 'res' => '10', 'date' => (string) strtotime('2026-11-20 10:00')], false, 'de_CH', strtotime(self::NOW));

        $this->assertSame('33_1', $state->moduleKey);
        $this->assertSame($module, $state->module);
        $this->assertSame($page, $state->page);
        $this->assertSame($this->types[7], $state->resourceType);
        $this->assertSame($this->resources[10], $state->resource);
        $this->assertEquals(new AppConfig('default'), $state->appConfig);
        $this->assertSame('monday', $state->beginWeek);
        // The date is moved to the first day of its week
        $this->assertSame(strtotime('2026-11-16 00:00'), $state->activeWeekStart);
        $this->assertSame(strtotime(self::FIRST_WEEK), $state->firstPermittedWeekStart);
        $this->assertSame(strtotime(self::LAST_WEEK), $state->lastPermittedWeekStart);
        $this->assertSame('de-CH', $state->language);
        $this->assertSame(10, $state->getResourceId());
    }

    public function testInvalidInputIsIgnored(): void
    {
        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['resType' => 'undefined', 'res' => '9 OR 1=1', 'date' => '-5'], false, 'de', strtotime(self::NOW));

        $this->assertNull($state->resourceType);
        $this->assertNull($state->resource);
        $this->assertSame(strtotime('2026-11-02 00:00'), $state->activeWeekStart);
    }

    public function testADirectLinkMayContainADate(): void
    {
        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['date' => '2026-11-20'], false, 'de', strtotime(self::NOW));
        $this->assertSame(strtotime('2026-11-16 00:00'), $state->activeWeekStart);

        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['date' => '2027-03-01'], false, 'de', strtotime(self::NOW));

        // Clamped to the last permitted week
        $this->assertSame(strtotime(self::LAST_WEEK), $state->activeWeekStart);

        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), ['date' => '2026-02-31'], false, 'de', strtotime(self::NOW));

        // Invalid date: current week
        $this->assertSame(strtotime('2026-11-02 00:00'), $state->activeWeekStart);
    }

    public function testTheAppStateTravelsWithTheRequest(): void
    {
        $state = $this->getFactory()->create('33_0', $this->getModule(), $this->createStub(PageModel::class), [], false, 'de', strtotime(self::NOW));
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
            'resourceBooking_resourceTypes' => implode(',', $resourceTypeIds),
            'resourceBooking_appConfig' => 'default',
        ]);
    }

    private function getFactory(bool $autoSelectFirstResource = true): AppStateFactory
    {
        $appConfigResolver = $this->createStub(AppConfigResolver::class);
        $appConfigResolver
            ->method('getAppConfig')
            ->willReturn(new AppConfig('default', $autoSelectFirstResource))
        ;

        $appConfigResolver
            ->method('getBeginWeek')
            ->willReturn('monday')
        ;

        $weekBoundaries = $this->createStub(WeekBoundaries::class);
        $weekBoundaries
            ->method('getFirstPermittedWeek')
            ->willReturn(strtotime(self::FIRST_WEEK))
        ;

        $weekBoundaries
            ->method('getLastPermittedWeek')
            ->willReturn(strtotime(self::LAST_WEEK))
        ;

        return new AppStateFactory($this->getFramework(), $appConfigResolver, $weekBoundaries);
    }

    private function getWeekFactory(int|null $firstWeek = null, int|null $lastWeek = null): AppStateFactory
    {
        $weekBoundaries = $this->createStub(WeekBoundaries::class);
        $weekBoundaries
            ->method('getFirstPermittedWeek')
            ->willReturn($firstWeek ?? strtotime('2000-01-03'))
        ;

        $weekBoundaries
            ->method('getLastPermittedWeek')
            ->willReturn($lastWeek ?? strtotime('2099-12-28'))
        ;

        return new AppStateFactory($this->getFramework(), $this->createStub(AppConfigResolver::class), $weekBoundaries);
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

        return $this->createContaoFrameworkStub([
            ResourceBookingResourceTypeModel::class => $typeAdapter,
            ResourceBookingResourceModel::class => $resourceAdapter,
        ]);
    }
}
