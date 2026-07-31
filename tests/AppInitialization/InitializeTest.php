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
        $framework = $this->mockContaoFramework([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => null]),
        ]);

        $initialize = $this->getInitialize($framework, new AttributeBag());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Module key not set.');

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);
    }

    public function testThrowsWhenModuleModelIsNotFound(): void
    {
        $framework = $this->mockContaoFramework([
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

        $framework = $this->mockContaoFramework([
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

        $framework = $this->mockContaoFramework([
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

        $controllerAdapter = $this->mockAdapter(['redirect']);

        // Contao's Controller::redirect() halts execution; emulate that with a sentinel.
        $controllerAdapter
            ->method('redirect')
            ->willThrowException(new \RuntimeException('redirect'))
        ;

        $framework = $this->mockContaoFramework([
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
        $singleRes = $this->singleItemCollection(9);

        $dateHelperAdapter = $this->mockAdapter(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
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

        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        $framework = $this->mockContaoFramework([
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
            ->willReturn(['intBackWeeks' => -2, 'intAheadWeeks' => 4])
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

    public function testDateStopInThePastClampsLastPermittedWeekToCurrentWeek(): void
    {
        $bag = new AttributeBag();
        // Pre-select a valid resType/res so the guards pass without auto-selection.
        $bag->set('resType', 7);
        $bag->set('res', 9);

        $moduleModel = $this->mockModuleModel([
            'resourceBooking_addDateStop' => true,
            'resourceBooking_dateStop' => 12345,
        ]);

        $dateHelperAdapter = $this->mockAdapter(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
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

        // Date stop resolves to a week (300) that lies before "now".
        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturn(300)
        ;

        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        $framework = $this->mockContaoFramework([
            ModuleKey::class => $this->mockAdapterWithReturnValues(['getModuleKey' => '33_0']),
            ModuleModel::class => $this->mockAdapterWithReturnValues(['findById' => $moduleModel]),
            PageModel::class => $this->mockAdapterWithReturnValues(['findById' => $this->mockPageModel()]),
            StringUtil::class => $this->mockAdapterWithReturnValues(['deserialize' => [7]]),
            ResourceBookingResourceTypeModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPk' => (object) ['id' => 7]]),
            ResourceBookingResourceModel::class => $this->mockAdapterWithReturnValues(['findPublishedByPkAndPid' => (object) ['id' => 9]]),
            DateHelper::class => $dateHelperAdapter,
            Date::class => $dateAdapter,
        ]);

        $utils = $this->createMock(Utils::class);
        $utils
            ->method('getAppConfig')
            ->willReturn(['intBackWeeks' => -2, 'intAheadWeeks' => 4])
        ;

        // getCurrentTime() is stubbed to a large value so 300 counts as "in the past".
        $initialize = $this->getInitialize($framework, $bag, null, $utils, 1_700_000_000);

        $initialize->initialize(self::MODULE_ID, self::PAGE_ID);

        // Last permitted week was clamped back to the current week (1000).
        $this->assertSame(1000, $bag->get('tstampLastPermittedWeek'));
        $this->assertSame('d1000', $bag->get('tstampLastPermittedWeekDate'));
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
        return $this->mockClassWithProperties(ModuleModel::class, array_merge([
            'id' => self::MODULE_ID,
            'resourceBooking_resourceTypes' => 'serialized-res-types',
            'resourceBooking_addDateStop' => false,
            'resourceBooking_dateStop' => 0,
        ], $properties));
    }

    private function mockPageModel(): PageModel
    {
        return $this->mockClassWithProperties(PageModel::class, ['id' => self::PAGE_ID]);
    }

    /**
     * @param array<string, mixed> $methodReturnValues
     */
    private function mockAdapterWithReturnValues(array $methodReturnValues): Adapter
    {
        $adapter = $this->mockAdapter(array_keys($methodReturnValues));

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
    private function singleItemCollection(int $id): object
    {
        return new class($id) {
            public function __construct(public readonly int $id)
            {
            }

            public function count(): int
            {
                return 1;
            }
        };
    }
}
