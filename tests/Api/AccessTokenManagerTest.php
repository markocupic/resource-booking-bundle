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

namespace Markocupic\ResourceBookingBundle\Tests\Api;

use Markocupic\ResourceBookingBundle\Api\AccessTokenManager;
use Markocupic\ResourceBookingBundle\Api\InvalidAccessTokenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class AccessTokenManagerTest extends TestCase
{
    public function testCreatesAndParsesAToken(): void
    {
        $clock = new MockClock('2026-11-18 20:00:00');
        $manager = new AccessTokenManager('secret', $clock, 3600);

        $token = $manager->parse($manager->create(33, 5, '33_1', 7));

        $this->assertSame(33, $token->moduleId);
        $this->assertSame(5, $token->pageId);
        $this->assertSame('33_1', $token->moduleKey);
        $this->assertSame(7, $token->memberId);
        $this->assertSame($clock->now()->getTimestamp() + 3600, $token->expiresAt);
    }

    public function testGuestTokensHaveNoMember(): void
    {
        $manager = new AccessTokenManager('secret', new MockClock());

        $this->assertNull($manager->parse($manager->create(33, 5, '33_0', null))->memberId);
    }

    public function testEveryTokenIsUnique(): void
    {
        $manager = new AccessTokenManager('secret', new MockClock());

        $this->assertNotSame($manager->create(33, 5, '33_0', 7), $manager->create(33, 5, '33_0', 7));
    }

    public function testExpiredTokensAreRejected(): void
    {
        $clock = new MockClock('2026-11-18 20:00:00');
        $manager = new AccessTokenManager('secret', $clock, 3600);
        $token = $manager->create(33, 5, '33_0', 7);

        $clock->sleep(3601);

        $this->expectException(InvalidAccessTokenException::class);
        $this->expectExceptionMessage('expired');

        $manager->parse($token);
    }

    /**
     * The browser can read the payload, but every change breaks the signature.
     */
    public function testManipulatedTokensAreRejected(): void
    {
        $manager = new AccessTokenManager('secret', new MockClock());
        [$payload, $signature] = explode('.', $manager->create(33, 5, '33_0', 7));

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/'), true), true);
        $data['member'] = 8;
        $manipulated = rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=').'.'.$signature;

        $this->expectException(InvalidAccessTokenException::class);
        $this->expectExceptionMessage('signature');

        $manager->parse($manipulated);
    }

    public function testTokensOfAnotherSecretAreRejected(): void
    {
        $token = (new AccessTokenManager('other secret', new MockClock()))->create(33, 5, '33_0', 7);

        $this->expectException(InvalidAccessTokenException::class);

        (new AccessTokenManager('secret', new MockClock()))->parse($token);
    }

    #[DataProvider('provideMalformedTokens')]
    public function testMalformedTokensAreRejected(string|null $token): void
    {
        $this->expectException(InvalidAccessTokenException::class);

        (new AccessTokenManager('secret', new MockClock()))->parse($token);
    }

    public static function provideMalformedTokens(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'no signature' => ['abc'];
        yield 'too many parts' => ['a.b.c'];
        yield 'garbage' => ['%%%.%%%'];
    }

    public function testATokenWithAValidSignatureButInvalidContentIsRejected(): void
    {
        $manager = new AccessTokenManager('secret', new MockClock());
        $payload = rtrim(strtr(base64_encode('{"mod":"33"}'), '+/', '-_'), '=');
        $key = hash_hmac('sha256', 'markocupic_resource_booking.api_access_token', 'secret', true);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '=');

        $this->expectException(InvalidAccessTokenException::class);
        $this->expectExceptionMessage('malformed');

        $manager->parse($payload.'.'.$signature);
    }
}
