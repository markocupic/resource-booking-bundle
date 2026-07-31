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

use Contao\CoreBundle\Routing\ScopeMatcher;
use Markocupic\ResourceBookingBundle\EventSubscriber\BackendAssetSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BackendAssetSubscriberTest extends TestCase
{
    /**
     * @var array<int, string>|null
     */
    private array|null $backupTlCss = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Preserve any pre-existing global state and start from a clean slate.
        $this->backupTlCss = $GLOBALS['TL_CSS'] ?? null;
        $GLOBALS['TL_CSS'] = [];
    }

    protected function tearDown(): void
    {
        if (null === $this->backupTlCss) {
            unset($GLOBALS['TL_CSS']);
        } else {
            $GLOBALS['TL_CSS'] = $this->backupTlCss;
        }

        parent::tearDown();
    }

    public function testSubscribesToTheKernelRequestEvent(): void
    {
        $this->assertSame(
            [KernelEvents::REQUEST => 'onKernelRequest'],
            BackendAssetSubscriber::getSubscribedEvents(),
        );
    }

    public function testRegistersTheBackendCssOnBackendRequests(): void
    {
        $scopeMatcher = $this->createMock(ScopeMatcher::class);
        $scopeMatcher
            ->method('isBackendRequest')
            ->willReturn(true)
        ;

        $packages = $this->createMock(Packages::class);
        $packages
            ->expects($this->once())
            ->method('getUrl')
            ->with('css/backend.css', 'markocupic_resource_booking')
            ->willReturn('/bundles/markocupicresourcebooking/css/backend.css')
        ;

        $subscriber = new BackendAssetSubscriber($scopeMatcher, $packages);
        $subscriber->onKernelRequest($this->mockRequestEvent());

        $this->assertContains('/bundles/markocupicresourcebooking/css/backend.css', $GLOBALS['TL_CSS']);
    }

    public function testDoesNotRegisterAnyCssOnNonBackendRequests(): void
    {
        $scopeMatcher = $this->createMock(ScopeMatcher::class);
        $scopeMatcher
            ->method('isBackendRequest')
            ->willReturn(false)
        ;

        $packages = $this->createMock(Packages::class);
        $packages
            ->expects($this->never())
            ->method('getUrl')
        ;

        $subscriber = new BackendAssetSubscriber($scopeMatcher, $packages);
        $subscriber->onKernelRequest($this->mockRequestEvent());

        $this->assertSame([], $GLOBALS['TL_CSS']);
    }

    private function mockRequestEvent(): RequestEvent
    {
        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn(Request::create('https://example.com/contao'))
        ;

        return $event;
    }
}
