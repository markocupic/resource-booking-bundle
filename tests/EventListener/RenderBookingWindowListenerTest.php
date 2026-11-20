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
use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelBookingFormController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\Api\Output\CalendarData;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\EventListener\RenderBookingWindowListener;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\WeekCalendar\CalendarDataBuilder;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekCalendarRenderer;
use Symfony\Component\HttpFoundation\Request;

class RenderBookingWindowListenerTest extends ContaoTestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    public function testRendersTheBookingWindowWithTheValuesSent(): void
    {
        $response = new AjaxResponse(BookingFormValidationController::ACTION);
        $response->setStatus(AjaxStatus::Warning);
        $response->setWarningMessage('Not available');
        $response->setFieldError('booking[description]', 'Too long');
        $response->setData('isBookable', false);

        $request = Request::create(
            '/_rbb/api/bookings/validate',
            'POST',
            [
                    'slots' => ['1-100-200-0', '1-300-400-0', ['manipulated']],
                    'bookingDescription' => 'Probe',
                    'nested' => ['a' => 'b'],
                ],
        );

        $this->invoke($request, $response);

        $this->assertSame('<html>', $response->getData(RenderBookingWindowListener::DATA_KEY));
        $this->assertSame(RenderBookingWindowListener::WINDOW_BOOKING, $this->context['bookingWindow']['action']);
        $this->assertTrue($this->context['bookingWindow']['showForm']);
        $this->assertFalse($this->context['bookingWindow']['response']['isBookable']);
        $this->assertSame(['1-100-200-0', '1-300-400-0'], $this->context['selectedSlots']);
        $this->assertSame('Probe', $this->context['form']['bookingDescription']);
        $this->assertSame('Not available', $this->context['messages']['warning']);
        $this->assertSame([['field' => 'booking[description]', 'id' => 'rbbFieldError_33_0_booking_description_', 'message' => 'Too long']], $this->context['fieldErrors']);

        // Data of the active week
        $this->assertSame(1700000000, $this->context['activeWeekStart']);
    }

    public function testTheFormIsHiddenAfterTheBooking(): void
    {
        $response = new AjaxResponse(BookingController::ACTION);
        $response->setStatus(AjaxStatus::Success);
        $response->setData('isBooked', true);

        $this->invoke(Request::create('/_rbb/api/bookings', 'POST'), $response);

        $this->assertFalse($this->context['bookingWindow']['showForm']);
    }

    public function testTheCancelFormIsOnlyDisplayedWhenTheWindowIsOpened(): void
    {
        $response = new AjaxResponse(CancelBookingFormController::ACTION);
        $response->setStatus(AjaxStatus::Success);
        $response->setData('booking', ['id' => 12, 'bookingUuid' => 'abc-12']);

        $this->invoke(Request::create('/_rbb/api/actions/cancelBookingFormRequest', 'POST'), $response);

        $this->assertSame(RenderBookingWindowListener::WINDOW_CANCEL, $this->context['bookingWindow']['action']);
        $this->assertTrue($this->context['bookingWindow']['showForm']);
        $this->assertSame(['id' => 12, 'bookingUuid' => 'abc-12'], $this->context['bookingWindow']['booking']);

        // Refused
        $refused = new AjaxResponse(CancelBookingFormController::ACTION);
        $refused->setStatus(AjaxStatus::Error);

        $this->invoke(Request::create('/_rbb/api/actions/cancelBookingFormRequest', 'POST'), $refused);

        $this->assertFalse($this->context['bookingWindow']['showForm']);
    }

    public function testAfterCancellingTheBookingOfTheRequestIsDisplayed(): void
    {
        $response = new AjaxResponse(CancelController::ACTION);
        $response->setStatus(AjaxStatus::Success);

        $request = Request::create('/_rbb/api/bookings/12?bookingUuid=abc-12&deleteRepetitions=1', 'DELETE');
        // The parameters of DELETE are sent in the query string (see apiFetch() in app.js)
        $request->attributes->set('id', '12');

        $this->invoke($request, $response);

        $this->assertFalse($this->context['bookingWindow']['showForm']);
        $this->assertSame(['id' => 12, 'bookingUuid' => 'abc-12'], $this->context['bookingWindow']['booking']);
        $this->assertSame('1', $this->context['form']['deleteRepetitions']);

        // Only a valid UUID is displayed
        $request = Request::create('/_rbb/api/bookings/12?bookingUuid=%3Cscript%3E', 'DELETE');
        $request->attributes->set('id', '12');

        $this->invoke($request, $response);

        $this->assertSame(['id' => 12, 'bookingUuid' => ''], $this->context['bookingWindow']['booking']);
    }

    public function testOtherActionsAreNotRendered(): void
    {
        $renderer = $this->createMock(WeekCalendarRenderer::class);
        $renderer
            ->expects($this->never())
            ->method('renderBlock')
        ;

        $listener = new RenderBookingWindowListener($renderer, $this->createStub(CalendarDataBuilder::class));
        $response = new AjaxResponse(RefreshDataController::ACTION);

        $listener(new AjaxResponseEvent($this->attachAppState(new Request()), $response));

        $this->assertNull($response->getData(RenderBookingWindowListener::DATA_KEY));
    }

    private function invoke(Request $request, AjaxResponse $response): void
    {
        $renderer = $this->createStub(WeekCalendarRenderer::class);
        $renderer
            ->method('renderBlock')
            ->willReturnCallback(
                function (AppState $appState, string $block, array $context): string {
                    $this->assertSame(RenderBookingWindowListener::BLOCK, $block);
                    $this->context = $context;

                    return '<html>';
                },
            )
        ;

        $calendarDataBuilder = $this->createStub(CalendarDataBuilder::class);
        $calendarDataBuilder
            ->method('build')
            ->willReturn(new CalendarData([], [], ['disabled' => true, 'tstamp' => 0], ['disabled' => true, 'tstamp' => 0], [], 0, 0, null, 1700000000, [], [], [], null, null, [], []))
        ;

        $listener = new RenderBookingWindowListener($renderer, $calendarDataBuilder);
        $listener(new AjaxResponseEvent($this->attachAppState($request), $response));
    }

    private function attachAppState(Request $request): Request
    {
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

        return $request;
    }
}
