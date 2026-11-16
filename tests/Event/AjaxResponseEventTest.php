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
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxResponseEventTest extends ContaoTestCase
{
    public function testProvidesRequestActionAppStateAndResponse(): void
    {
        $request = Request::create('https://example.com/calendar', 'POST', ['action' => 'applyFilterRequest']);

        $appState = new AppState(
            '33_0',
            $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33]),
            $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]),
            null,
            null,
            ['beginWeek' => 'monday', 'autoSelectFirstResource' => true, 'permittedFormFields' => []],
            0,
            0,
            0,
            'de',
        );

        $appState->attachTo($request);

        $ajaxResponse = new AjaxResponse('applyFilterRequest');
        $event = new AjaxResponseEvent($request, $ajaxResponse);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame('applyFilterRequest', $event->getAction());
        $this->assertSame($appState, $event->getAppState());
        $this->assertSame($ajaxResponse, $event->getAjaxResponse());
    }
}
