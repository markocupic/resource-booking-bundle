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

namespace Markocupic\ResourceBookingBundle\Tests\EventSubscriber;

use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\EventSubscriber\AjaxRequestEventSubscriber;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AjaxRequestEventSubscriberTest extends TestCase
{
    public function testSubscribesToTheAjaxRequestEventWithHighPriority(): void
    {
        $this->assertSame(
            [
                AjaxRequestEvent::class => ['onXmlHttpRequest', 1000],
            ],
            AjaxRequestEventSubscriber::getSubscribedEvents(),
        );

        $this->assertSame(1000, AjaxRequestEventSubscriber::PRIORITY);
    }

    public function testAddAndGetRegisterAndReturnControllersByAlias(): void
    {
        $controller = $this->createMock(ControllerInterface::class);

        $subscriber = new AjaxRequestEventSubscriber(new RequestStack());
        $subscriber->add($controller, 'showBookingForm');

        $this->assertSame($controller, $subscriber->get('showBookingForm'));
    }

    public function testGetThrowsWhenNoControllerIsRegisteredForTheAlias(): void
    {
        $subscriber = new AjaxRequestEventSubscriber(new RequestStack());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not find Controller for action "unknown".');

        $subscriber->get('unknown');
    }

    public function testOnXmlHttpRequestDelegatesToTheMatchingControllerAndStoresItsResponse(): void
    {
        $request = $this->createXmlHttpRequest('showBookingFormRequest');
        $incomingResponse = new AjaxResponse('showBookingFormRequest');
        $generatedResponse = new AjaxResponse('showBookingFormRequest');

        $controller = $this->createMock(ControllerInterface::class);
        $controller
            ->expects($this->once())
            ->method('generateResponse')
            ->with($request, $incomingResponse)
            ->willReturn($generatedResponse)
        ;

        // The "Request" suffix of the action is stripped to build the alias.
        $subscriber = new AjaxRequestEventSubscriber($this->createRequestStack($request));
        $subscriber->add($controller, 'showBookingForm');

        $event = new AjaxRequestEvent($request, $incomingResponse);
        $subscriber->onXmlHttpRequest($event);

        $this->assertSame($generatedResponse, $event->getAjaxResponse());
    }

    public function testOnXmlHttpRequestDoesNothingForNonXmlHttpRequests(): void
    {
        $request = Request::create('https://example.com/calendar', 'POST', ['action' => 'showBookingFormRequest']);
        $incomingResponse = new AjaxResponse('showBookingFormRequest');

        $controller = $this->createMock(ControllerInterface::class);
        $controller
            ->expects($this->never())
            ->method('generateResponse')
        ;

        $subscriber = new AjaxRequestEventSubscriber($this->createRequestStack($request));
        $subscriber->add($controller, 'showBookingForm');

        $event = new AjaxRequestEvent($request, $incomingResponse);
        $subscriber->onXmlHttpRequest($event);

        // The response is left untouched.
        $this->assertSame($incomingResponse, $event->getAjaxResponse());
    }

    public function testOnXmlHttpRequestDoesNothingWhenThereIsNoCurrentRequest(): void
    {
        $incomingResponse = new AjaxResponse('showBookingFormRequest');

        $controller = $this->createMock(ControllerInterface::class);
        $controller
            ->expects($this->never())
            ->method('generateResponse')
        ;

        // Empty request stack -> getCurrentRequest() returns null.
        $subscriber = new AjaxRequestEventSubscriber(new RequestStack());
        $subscriber->add($controller, 'showBookingForm');

        $event = new AjaxRequestEvent(
            Request::create('https://example.com/calendar'),
            $incomingResponse,
        );
        $subscriber->onXmlHttpRequest($event);

        $this->assertSame($incomingResponse, $event->getAjaxResponse());
    }

    public function testOnXmlHttpRequestThrowsForAnUnknownAction(): void
    {
        $request = $this->createXmlHttpRequest('somethingWeirdRequest');

        $subscriber = new AjaxRequestEventSubscriber($this->createRequestStack($request));

        $event = new AjaxRequestEvent($request, new AjaxResponse('somethingWeirdRequest'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not find Controller for action "somethingWeird".');

        $subscriber->onXmlHttpRequest($event);
    }

    private function createXmlHttpRequest(string $action): Request
    {
        return Request::create(
            'https://example.com/calendar',
            'POST',
            ['action' => $action],
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
    }

    private function createRequestStack(Request $request): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }
}
