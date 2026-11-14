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

namespace Markocupic\ResourceBookingBundle\Tests\AppInitialization;

use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class InitializeTest extends ContaoTestCase
{
    private const MODULE_ID = 33;

    private const PAGE_ID = 5;

    public function testThrowsWhenModuleKeyIsNotSet(): void
    {
        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => null]),
        ]);

        $initialize = $this->getInitialize($framework, new AttributeBag());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Module key not set.');

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);
    }

    public function testThrowsWhenModuleModelIsNotFound(): void
    {
        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => null]),
        ]);

        $initialize = $this->getInitialize($framework, new AttributeBag());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Module id not set.');

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);
    }

    public function testThrowsWhenPageModelIsNotFoundButStoresModuleModelIdFirst(): void
    {
        $bag = new AttributeBag();

        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => null]),
        ]);

        $initialize = $this->getInitialize($framework, $bag);

        try {
            $initialize->initialize(self::MODULE_ID, self::PAGE_ID);
            $this->fail('Expected an exception to be thrown.');
        } catch (\Exception $e) {
            $this->assertSame('Page model not set.', $e->getMessage());
        }

        // The module model id is stored before the page model is resolved.
        $this->assertSame(self::MODULE_ID, $bag->get('moduleModelId'));
    }

    public function testThrowsUnauthorizedWhenActiveResTypeIsForbidden(): void
    {
        $bag = new AttributeBag();
        $bag->set('resType', 9); // > 0, but not part of the allowed list below

        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            StringUtil::class => $this->mockAdapterWithReturnValues(['deserialize' => [1, 2]]),
            ResourceBookingResourceTypeModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPk' => null]),
        ]);

        $initialize = $this->getInitialize($framework, $bag);

        try {
            $initialize->initialize(self::MODULE_ID, self::PAGE_ID);
            $this->fail('Expected an UnauthorizedHttpException to be thrown.');
        } catch (UnauthorizedHttpException $e) {
            // Note: UnauthorizedHttpException's first constructor argument is the
            // "challenge" (WWW-Authenticate header), not the message. The code
            // passes the human-readable text there, so it ends up in the header
            // and getMessage() stays empty.
            $this->assertStringContainsString(
                'Unauthorized access to resource type with ID 9.',
                $e->getHeaders()['WWW-Authenticate'] ?? '',
            );
        }
    }

    public function testAppliesResTypeUrlParamAndRedirects(): void
    {
        $bag = new AttributeBag();

        $controllerAdapter = $this->createAdapterMock(['redirect']);

        // Contao's Controller::redirect() halts execution; emulate that with a sentinel.
        $controllerAdapter
            ->method('redirect')
            ->willThrowException(new \RuntimeException('redirect'))
        ;

        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            Controller::class => $controllerAdapter,
        ]);

        $request = Request::create('https://example.com/calendar?resType=4');
        $initialize = $this->getInitialize($framework, $bag, $request);

        try {
            $initialize->initialize(self::MODULE_ID, self::PAGE_ID);
            $this->fail('Expected the redirect sentinel exception to be thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('redirect', $e->getMessage());
        }

        // The resType url param was written to the session before redirecting.
        $this->assertSame('4', $bag->get('resType'));
    }

    public function testHappyPathAutoSelectsSingleResTypeAndResAndStoresWeekBoundaries(): void
    {
        $bag = new AttributeBag();

        $singleResType = $this->singleItemCollection(7);
        $singleRes = $this->singleItemCollection(9, $this->mockResourceModel(9));

        $dateHelperAdapter = $this->createAdapterMock(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
        $dateHelperAdapter
            ->method('getFirstDayOfCurrentWeek')
            ->willReturn(1000)
        ;

        $dateHelperAdapter
            ->method('addWeeksToTime')
            ->willReturnMap([
                [-2, 1000, 500],
                [4, 1000, 2000],
            ])
        ;

        // The active week already is the first day of its week.
        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturnArgument(1)
        ;

        $dateAdapter = $this->createAdapterMock(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            StringUtil::class => $this->mockAdapterWithReturnValues(['deserialize' => [10, 20]]),
            ResourceBookingResourceTypeModel::class => $this->mockAdapterWithReturnValues(['findPublishedByIds' => $singleResType]),
            ResourceBookingResourceModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPid' => $singleRes]),
            DateHelper::class => $dateHelperAdapter,
            Date::class => $dateAdapter,
        ]);

        $utils = $this->createMock(Utils::class);
        $utils
            ->method('getAppConfig')
            ->willReturn(['beginWeek' => 'monday'])
        ;

        $request = Request::create('https://example.com/calendar');
        $request->setLocale('de');

        $initialize = $this->getInitialize($framework, $bag, $request, $utils);

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        $this->assertSame(self::MODULE_ID, $bag->get('moduleModelId'));
        $this->assertSame(self::PAGE_ID, $bag->get('pageModelId'));
        $this->assertSame(7, $bag->get('resType'));
        $this->assertSame(9, $bag->get('res'));
        $this->assertSame(1000, $bag->get('activeWeekTstamp'));
        $this->assertSame('d1000', $bag->get('activeWeekDate'));
        $this->assertSame(500, $bag->get('tstampFirstPermittedWeek'));
        $this->assertSame('d500', $bag->get('tstampFirstPermittedDate'));
        $this->assertSame(2000, $bag->get('tstampLastPermittedWeek'));
        $this->assertSame('d2000', $bag->get('tstampLastPermittedWeekDate'));
        $this->assertSame('de', $bag->get('language'));
    }

    public function testBookableUntilInThePastClampsLastPermittedWeekToCurrentWeek(): void
    {
        $bag = new AttributeBag();
        // Pre-select a valid resType/res so the guards pass without auto-selection.
        $bag->set('resType', 7);
        $bag->set('res', 9);

        $resourceModel = $this->mockResourceModel(9, [
            'addBookableUntil' => true,
            'bookableUntil' => '12345',
        ]);

        $dateHelperAdapter = $this->createAdapterMock(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
        $dateHelperAdapter
            ->method('getFirstDayOfCurrentWeek')
            ->willReturn(1000)
        ;

        $dateHelperAdapter
            ->method('addWeeksToTime')
            ->willReturnMap([
                [-2, 1000, 500],
                [4, 1000, 2000],
            ])
        ;

        // "Bookable until" resolves to a week (300) that lies before "now".
        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturn(300)
        ;

        $dateAdapter = $this->createAdapterMock(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            StringUtil::class => $this->mockAdapterWithReturnValues(['deserialize' => [7]]),
            ResourceBookingResourceTypeModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPk' => (object) ['id' => 7]]),
            ResourceBookingResourceModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPkAndPid' => $resourceModel]),
            DateHelper::class => $dateHelperAdapter,
            Date::class => $dateAdapter,
        ]);

        $utils = $this->createMock(Utils::class);
        $utils
            ->method('getAppConfig')
            ->willReturn(['beginWeek' => 'monday'])
        ;

        // getCurrentTime() is stubbed to a large value so 300 counts as "in the past".
        $initialize = $this->getInitialize($framework, $bag, null, $utils, 1_700_000_000);

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        // Last permitted week was clamped back to the current week (1000).
        $this->assertSame(1000, $bag->get('tstampLastPermittedWeek'));
        $this->assertSame('d1000', $bag->get('tstampLastPermittedWeekDate'));
    }

    public function testActiveWeekIsClampedToThePermittedWeeksOfTheResource(): void
    {
        $bag = new AttributeBag();
        $bag->set('resType', 7);
        $bag->set('res', 9);
        // Lies after the last permitted week of the resource (2000).
        $bag->set('activeWeekTstamp', 9999);

        $dateHelperAdapter = $this->createAdapterMock(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
        $dateHelperAdapter
            ->method('getFirstDayOfCurrentWeek')
            ->willReturn(1000)
        ;

        $dateHelperAdapter
            ->method('addWeeksToTime')
            ->willReturnMap([
                [-2, 1000, 500],
                [4, 1000, 2000],
            ])
        ;

        // The active week already is the first day of its week.
        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturnArgument(1)
        ;

        $dateAdapter = $this->createAdapterMock(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        $framework = $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            StringUtil::class => $this->mockAdapterWithReturnValues(['deserialize' => [7]]),
            ResourceBookingResourceTypeModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPk' => (object) ['id' => 7]]),
            ResourceBookingResourceModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPkAndPid' => $this->mockResourceModel(9)]),
            DateHelper::class => $dateHelperAdapter,
            Date::class => $dateAdapter,
        ]);

        $utils = $this->createMock(Utils::class);
        $utils
            ->method('getAppConfig')
            ->willReturn(['beginWeek' => 'monday'])
        ;

        $initialize = $this->getInitialize($framework, $bag, null, $utils);

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        $this->assertSame(500, $bag->get('tstampFirstPermittedWeek'));
        $this->assertSame(2000, $bag->get('tstampLastPermittedWeek'));
        $this->assertSame(2000, $bag->get('activeWeekTstamp'));
        $this->assertSame('d2000', $bag->get('activeWeekDate'));
    }

    public function testAutoSelectsTheFirstAvailableResourceIfTheSessionIsEmpty(): void
    {
        $bag = new AttributeBag();

        // Resource type 7 has no published resource, resource type 8 has resource 20.
        $resourceTypeAdapter = $this->mockAdapterWithReturnValues([
            'findPublishedByIds' => [(object) ['id' => 7], (object) ['id' => 8]],
            'findPublishedByPk' => (object) ['id' => 8],
        ]);

        $resourceModel = $this->mockResourceModel(20);

        $resourceAdapter = $this->createAdapterMock(['findPublishedByPid', 'findPublishedByPkAndPid']);
        $resourceAdapter
            ->method('findPublishedByPid')
            ->willReturnCallback(fn (int $pid): object|null => 8 === $pid ? $this->singleItemCollection(20, $resourceModel) : null)
        ;

        $resourceAdapter
            ->method('findPublishedByPkAndPid')
            ->with(20, 8)
            ->willReturn($resourceModel)
        ;

        $initialize = $this->getInitialize(
            $this->frameworkForAutoSelect($resourceTypeAdapter, $resourceAdapter),
            $bag,
            null,
            $this->utilsWithAutoSelect(true),
        );

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        $this->assertSame(8, $bag->get('resType'));
        $this->assertSame(20, $bag->get('res'));
    }

    public function testDoesNotAutoSelectTheFirstResourceIfDisabled(): void
    {
        $bag = new AttributeBag();

        $resourceAdapter = $this->createAdapterMock(['findPublishedByPid', 'findPublishedByPkAndPid']);
        $resourceAdapter
            ->expects($this->never())
            ->method('findPublishedByPid')
        ;

        $initialize = $this->getInitialize(
            $this->frameworkForAutoSelect($this->mockAdapterWithReturnValues(['findPublishedByIds' => null]), $resourceAdapter),
            $bag,
            null,
            $this->utilsWithAutoSelect(false),
        );

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        $this->assertFalse($bag->has('res'));
    }

    public function testDoesNotAutoSelectIfAResourceTypeIsAlreadySelected(): void
    {
        $bag = new AttributeBag();
        $bag->set('resType', 7);

        $resourceTypeAdapter = $this->createAdapterMock(['findPublishedByIds', 'findPublishedByPk']);
        $resourceTypeAdapter
            ->expects($this->never())
            ->method('findPublishedByIds')
        ;

        $resourceTypeAdapter
            ->method('findPublishedByPk')
            ->willReturn((object) ['id' => 7])
        ;

        $initialize = $this->getInitialize(
            $this->frameworkForAutoSelect($resourceTypeAdapter, $this->mockAdapterWithReturnValues(['findPublishedByPid' => null])),
            $bag,
            null,
            $this->utilsWithAutoSelect(true),
        );

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        $this->assertSame(7, $bag->get('resType'));
        $this->assertFalse($bag->has('res'));
    }

    private function frameworkForAutoSelect(Adapter $resourceTypeAdapter, Adapter $resourceAdapter): ContaoFramework
    {
        $dateHelperAdapter = $this->createAdapterMock(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
        $dateHelperAdapter
            ->method('getFirstDayOfCurrentWeek')
            ->willReturn(1000)
        ;

        $dateHelperAdapter
            ->method('addWeeksToTime')
            ->willReturnCallback(static fn (int $weeks, int $time): int => $time + $weeks * 100)
        ;

        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturnArgument(1)
        ;

        $dateAdapter = $this->createAdapterMock(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        return $this->createContaoFrameworkMock([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockModuleModel()]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            StringUtil::class => $this->mockAdapterWithReturnValues(['deserialize' => [7, 8]]),
            ResourceBookingResourceTypeModel::class => $resourceTypeAdapter,
            ResourceBookingResourceModel::class => $resourceAdapter,
            DateHelper::class => $dateHelperAdapter,
            Date::class => $dateAdapter,
        ]);
    }

    private function utilsWithAutoSelect(bool $enabled): Utils
    {
        $utils = $this->createMock(Utils::class);
        $utils
            ->method('getAppConfig')
            ->willReturn(['beginWeek' => 'monday'])
        ;

        $utils
            ->method('getAppConfigByName')
            ->willReturn(['autoSelectFirstResource' => $enabled, 'permittedFormFields' => []])
        ;

        return $utils;
    }

    /**
     * Builds an Initialize instance whose two environment seams (getSessionBag()
     * and getCurrentTime()) are stubbed, while everything else runs for real.
     *
     * @return Initialize&MockObject
     */
    private function getInitialize(ContaoFramework $framework, SessionBagInterface $sessionBag, Request|null $request = null, Utils|null $utils = null, int $currentTime = 1_700_000_000): Initialize
    {
        $requestStack = new RequestStack();
        $requestStack->push($request ?? Request::create('https://example.com/calendar'));

        $initialize = $this->getMockBuilder(Initialize::class)
            ->setConstructorArgs([
                $framework,
                $requestStack,
                $utils ?? $this->createMock(Utils::class),
                new WeekBoundaries($framework),
                'contao_resource_booking_bundle_attributes',
            ])
            ->onlyMethods(['getSessionBag', 'getCurrentTime'])
            ->getMock()
        ;

        $initialize
            ->method('getSessionBag')
            ->willReturn($sessionBag)
        ;

        $initialize
            ->method('getCurrentTime')
            ->willReturn($currentTime)
        ;

        return $initialize;
    }

    private function mockModuleModel(array $properties = []): ModuleModel
    {
        return $this->createClassWithPropertiesMock(ModuleModel::class, array_merge([
            'id' => self::MODULE_ID,
            'resourceBooking_resourceTypes' => 'serialized-res-types',
        ], $properties));
    }

    private function mockResourceModel(int $id, array $properties = []): ResourceBookingResourceModel
    {
        return $this->createClassWithPropertiesMock(ResourceBookingResourceModel::class, array_merge([
            'id' => $id,
            // Weeks back/ahead are set per resource
            'weeksBack' => 2,
            'weeksAhead' => 4,
            'addBookableUntil' => false,
            'bookableUntil' => '',
        ], $properties));
    }

    private function mockPageModel(): PageModel
    {
        return $this->createClassWithPropertiesMock(PageModel::class, ['id' => self::PAGE_ID]);
    }

    /**
     * @param array<string, mixed> $methodReturnValues
     */
    private function mockAdapterWithReturnValues(array $methodReturnValues): Adapter
    {
        $adapter = $this->createAdapterMock(array_keys($methodReturnValues));

        foreach ($methodReturnValues as $method => $value) {
            $adapter
                ->method($method)
                ->willReturn($value)
            ;
        }

        return $adapter;
    }

    /**
     * A minimal stand-in for a Contao model collection holding exactly one item.
     */
    private function singleItemCollection(int $id, object|null $current = null): object
    {
        return new class($id, $current) {
            public function __construct(
                public readonly int $id,
                private readonly object|null $current,
            ) {
            }

            public function count(): int
            {
                return 1;
            }

            public function current(): object|null
            {
                return $this->current;
            }
        };
    }
}
