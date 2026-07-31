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
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class PostCancellingEventTest extends TestCase
{
    public function testGettersReturnTheConstructorArguments(): void
    {
        $request = $this->createMock(Request::class);
        $ajaxResponse = new AjaxResponse('cancel');
        $sessionBag = $this->createMock(ArrayAttributeBag::class);
        $user = $this->createMock(UserInterface::class);
        $collection = $this->createMock(Collection::class);

        $event = new PostCancellingEvent($request, $ajaxResponse, $sessionBag, $user, $collection);

        $this->assertSame($request, $event->getRequest());
        $this->assertSame($ajaxResponse, $event->getAjaxResponse());
        $this->assertSame($sessionBag, $event->getSessionBag());
        $this->assertSame($user, $event->getUser());
        $this->assertSame($collection, $event->getBookingCollection());
    }

    public function testBookingCollectionMayBeNull(): void
    {
        $event = new PostCancellingEvent(
            $this->createMock(Request::class),
            new AjaxResponse('cancel'),
            $this->createMock(ArrayAttributeBag::class),
            $this->createMock(UserInterface::class),
            null,
        );

        $this->assertNull($event->getBookingCollection());
    }
}
