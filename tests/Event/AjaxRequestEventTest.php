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

namespace Markocupic\ResourceBookingBundle\Tests\Event;

use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class AjaxRequestEventTest extends TestCase
{
    public function testExposesRequestAndAjaxResponse(): void
    {
        $request = $this->createMock(Request::class);
        $ajaxResponse = new AjaxResponse('someAction');

        $event = new AjaxRequestEvent($request, $ajaxResponse);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($ajaxResponse, $event->getAjaxResponse());
    }

    public function testAjaxResponseCanBeReplaced(): void
    {
        $event = new AjaxRequestEvent($this->createMock(Request::class), new AjaxResponse('a'));

        $replacement = new AjaxResponse('b');
        $event->setAjaxResponse($replacement);

        $this->assertSame($replacement, $event->getAjaxResponse());
    }
}
