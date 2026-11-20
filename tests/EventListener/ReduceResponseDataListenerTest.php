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

namespace Markocupic\ResourceBookingBundle\Tests\EventListener;

use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\EventListener\ReduceResponseDataListener;
use Markocupic\ResourceBookingBundle\EventListener\RenderBookingWindowListener;
use Markocupic\ResourceBookingBundle\EventListener\RenderWeekCalendarListener;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;

class ReduceResponseDataListenerTest extends ContaoTestCase
{
    public function testOnlyTheHtmlAndSomeValuesOfTheWeekCalendarAreSent(): void
    {
        $response = new AjaxResponse(RefreshDataController::ACTION);
        $response->mergeData([
            RenderWeekCalendarListener::DATA_KEY => '<div>week</div>',
            'rows' => [['cellData' => []]],
            'filterBoard' => [],
            'loggedInUser' => ['firstname' => 'Anna'],
            'activeResourceTypeId' => 1,
            'activeResourceId' => 2,
            'activeWeekStart' => 1795392000,
        ]);

        (new ReduceResponseDataListener())($this->createEvent($response));

        $this->assertSame(
            [
                RenderWeekCalendarListener::DATA_KEY => '<div>week</div>',
                'activeResourceTypeId' => 1,
                'activeResourceId' => 2,
                'activeWeekStart' => 1795392000,
            ],
            $response->getAllData(),
        );
    }

    public function testOnlyTheHtmlAndTheResultOfTheBookingWindowAreSent(): void
    {
        $response = new AjaxResponse(BookingFormValidationController::ACTION);
        $response->mergeData([RenderBookingWindowListener::DATA_KEY => '<form>', 'isBookable' => true, 'slots' => [['id' => 1]], 'rows' => []]);

        (new ReduceResponseDataListener())($this->createEvent($response));

        $this->assertSame([RenderBookingWindowListener::DATA_KEY => '<form>', 'isBookable' => true], $response->getAllData());
    }

    public function testTheDataIsKeptIfNoHtmlWasRenderedOrForOtherActions(): void
    {
        $listener = new ReduceResponseDataListener();

        // Error of the week calendar: nothing rendered
        $failed = new AjaxResponse(RefreshDataController::ACTION);
        $failed->setData('rows', []);
        $listener($this->createEvent($failed));

        // Action of another extension
        $custom = new AjaxResponse('myCustomRequest');
        $custom->mergeData(['resourceId' => 3, RenderWeekCalendarListener::DATA_KEY => '<div>']);
        $listener($this->createEvent($custom));

        $this->assertSame(['rows' => []], $failed->getAllData());
        $this->assertSame(['resourceId' => 3, RenderWeekCalendarListener::DATA_KEY => '<div>'], $custom->getAllData());
    }

    public function testRunsAfterTheHtmlHasBeenRendered(): void
    {
        $priority = static fn (string $class): int => (new \ReflectionClass($class))->getAttributes()[0]->newInstance()->priority;

        $this->assertLessThan($priority(RenderWeekCalendarListener::class), $priority(ReduceResponseDataListener::class));
        $this->assertLessThan($priority(RenderBookingWindowListener::class), $priority(ReduceResponseDataListener::class));
    }

    private function createEvent(AjaxResponse $response): AjaxResponseEvent
    {
        $request = new Request();

        (new AppState(
            '33_0',
            $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33]),
            $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]),
            null,
            null,
            new AppConfig(),
            'monday',
            0,
            0,
            0,
            'de',
        ))->attachTo($request);

        return new AjaxResponseEvent($request, $response);
    }
}
