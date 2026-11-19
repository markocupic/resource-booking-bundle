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

use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxResponseEventTest extends ContaoTestCase
{
    public function testProvidesRequestActionAppStateAndResponse(): void
    {
        $request = Request::create('https://example.com/_rbb/api/calendar');

        $appState = new AppState(
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
        );

        $appState->attachTo($request);

        $ajaxResponse = new AjaxResponse('refreshDataRequest');
        $event = new AjaxResponseEvent($request, $ajaxResponse);

        $this->assertSame($request, $event->getRequest());
        // The action comes from the response (the API route sets it)
        $this->assertSame('refreshDataRequest', $event->getAction());
        $this->assertSame($appState, $event->getAppState());
        $this->assertSame($ajaxResponse, $event->getAjaxResponse());
    }
}
