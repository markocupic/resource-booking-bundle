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

namespace Markocupic\ResourceBookingBundle\Tests\Session\Attribute;

use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ArrayAttributeBagTest extends TestCase
{
    public function testSetAndGetRoundTrip(): void
    {
        $bag = $this->createBag();
        $bag->set('foo', 'bar');

        $this->assertSame('bar', $bag->get('foo'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $bag = $this->createBag();

        $this->assertNull($bag->get('missing'));
        $this->assertSame('fallback', $bag->get('missing', 'fallback'));
    }

    public function testHasReflectsStoredKeys(): void
    {
        $bag = $this->createBag();

        $this->assertFalse($bag->has('foo'));

        $bag->set('foo', 'bar');

        $this->assertTrue($bag->has('foo'));
    }

    public function testCountReflectsNumberOfStoredKeys(): void
    {
        $bag = $this->createBag();

        $this->assertSame(0, $bag->count());

        $bag->set('a', 1);
        $bag->set('b', 2);

        $this->assertSame(2, $bag->count());
    }

    public function testRemoveDeletesTheKey(): void
    {
        $bag = $this->createBag();
        $bag->set('a', 1);
        $bag->set('b', 2);

        $removed = $bag->remove('a');

        // remove() returns the removed value (AttributeBagInterface contract).
        $this->assertSame(1, $removed);
        $this->assertNull($bag->remove('does-not-exist'));

        $this->assertFalse($bag->has('a'));
        $this->assertTrue($bag->has('b'));
        $this->assertSame(1, $bag->count());
    }

    public function testReplaceMergesAttributes(): void
    {
        $bag = $this->createBag();
        $bag->set('a', 1);

        $bag->replace(['b' => 2, 'a' => 99]);

        $this->assertSame(99, $bag->get('a'));
        $this->assertSame(2, $bag->get('b'));
        $this->assertSame(2, $bag->count());
    }

    public function testOffsetSetAndOffsetExistsUseTheNamespacedStore(): void
    {
        $bag = $this->createBag();
        $bag['foo'] = 'bar';

        $this->assertTrue($bag->offsetExists('foo'));
        $this->assertSame('bar', $bag->get('foo'));
    }

    public function testOffsetGetReadsFromTheNamespacedStore(): void
    {
        $bag = $this->createBag();
        $bag->set('foo', 'bar');

        // offsetGet must return what set()/offsetSet() stored, not raw parent data.
        $this->assertSame('bar', $bag['foo']);
        $this->assertNull($bag['missing']);
    }

    public function testClearRemovesAllNamespacedData(): void
    {
        $bag = $this->createBag();
        $bag->set('a', 1);
        $bag->set('b', 2);

        $bag->clear();

        $this->assertSame(0, $bag->count());
        $this->assertFalse($bag->has('a'));
        $this->assertFalse($bag->has('b'));
    }

    /**
     * Builds a bag backed by an XHR request that carries a module key and a
     * matching token, so getSessionBagKey() resolves to a stable, non-empty key.
     */
    private function createBag(): ArrayAttributeBag
    {
        $request = Request::create(
            'https://example.com/calendar?token_33_0=abc',
            'POST',
            ['moduleKey' => '33_0'],
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );

        // An unstarted session is enough: getSessionBagKey() skips the session id then.
        $session = $this->createMock(SessionInterface::class);
        $session
            ->method('isStarted')
            ->willReturn(false)
        ;
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        // No token -> no user id contribution to the key.
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn(null)
        ;

        return new ArrayAttributeBag($requestStack, $tokenStorage, '_sf2_attributes');
    }
}
