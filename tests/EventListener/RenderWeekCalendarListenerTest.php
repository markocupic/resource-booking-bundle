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
use Markocupic\ResourceBookingBundle\AjaxController\BookingController;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\EventListener\RenderWeekCalendarListener;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekCalendarRenderer;
use Symfony\Component\HttpFoundation\Request;

class RenderWeekCalendarListenerTest extends ContaoTestCase
{
    public function testAddsTheHtmlOfTheWeekViewRenderedWithTheDataOfTheResponse(): void
    {
        $response = new AjaxResponse(RefreshDataController::ACTION);
        $response->setStatus(AjaxStatus::Success);
        $response->setData('rows', ['row']);

        $renderer = $this->createMock(WeekCalendarRenderer::class);
        $renderer
            ->expects($this->once())
            ->method('render')
            ->with($this->isInstanceOf(AppState::class), ['rows' => ['row']])
            ->willReturn('<div>week</div>')
        ;

        (new RenderWeekCalendarListener($renderer))($this->createEvent($response));

        $this->assertSame('<div>week</div>', $response->getData(RenderWeekCalendarListener::DATA_KEY));
    }

    public function testOnlyTheDataOfTheWeekCalendarIsRendered(): void
    {
        $renderer = $this->createMock(WeekCalendarRenderer::class);
        $renderer
            ->expects($this->never())
            ->method('render')
        ;

        $listener = new RenderWeekCalendarListener($renderer);

        // Another action
        $response = new AjaxResponse(BookingController::ACTION);
        $response->setStatus(AjaxStatus::Success);
        $listener($this->createEvent($response));

        // Failed request of the week calendar
        $failed = new AjaxResponse(RefreshDataController::ACTION);
        $failed->setStatus(AjaxStatus::Error);
        $listener($this->createEvent($failed));

        $this->assertNull($response->getData(RenderWeekCalendarListener::DATA_KEY));
        $this->assertNull($failed->getData(RenderWeekCalendarListener::DATA_KEY));
    }

    public function testRunsAfterTheOtherListeners(): void
    {
        $attribute = (new \ReflectionClass(RenderWeekCalendarListener::class))->getAttributes()[0]->newInstance();

        $this->assertLessThan(0, $attribute->priority);
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
