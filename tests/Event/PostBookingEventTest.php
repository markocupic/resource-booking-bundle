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

use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class PostBookingEventTest extends TestCase
{
    public function testGettersReturnTheConstructorArguments(): void
    {
        $request = $this->createStub(Request::class);
        $ajaxResponse = new AjaxResponse('book');
        $appState = $this->createAppState();
        $user = $this->createStub(UserInterface::class);
        $collection = $this->createStub(Collection::class);

        $event = new PostBookingEvent($request, $ajaxResponse, $appState, $user, $collection);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($ajaxResponse, $event->getAjaxResponse());
        $this->assertSame($appState, $event->getAppState());
        $this->assertSame($user, $event->getUser());
        $this->assertSame($collection, $event->getBookingCollection());
    }

    public function testBookingCollectionMayBeNull(): void
    {
        $event = new PostBookingEvent(
            $this->createStub(Request::class),
            new AjaxResponse('book'),
            $this->createAppState(),
            $this->createStub(UserInterface::class),
            null,
        );

        $this->assertNull($event->getBookingCollection());
    }

    private function createAppState(): AppState
    {
        return new AppState(
            '33_0',
            $this->createStub(ModuleModel::class),
            $this->createStub(PageModel::class),
            null,
            null,
            new AppConfig(),
            'monday',
            0,
            0,
            0,
            'de',
        );
    }
}
