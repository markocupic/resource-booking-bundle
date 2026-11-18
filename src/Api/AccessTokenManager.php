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

namespace Markocupic\ResourceBookingBundle\Api;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates and verifies the access tokens of the week calendar API.
 *
 * Format: base64url(JSON payload) "." base64url(HMAC-SHA256 signature). The signature
 * key is derived from the kernel secret, the browser can read the payload but cannot
 * change it. The token is sent in the HTTP header X-Rbb-Access-Token (not in the URL,
 * so it does not end up in log files or the browser history).
 */
class AccessTokenManager
{
    public const HEADER = 'X-Rbb-Access-Token';

    /**
     * Validity of a token: the page has to be reloaded afterwards.
     */
    public const DEFAULT_TTL = 8 * 3600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        private readonly ClockInterface $clock,
        private readonly int $ttl = self::DEFAULT_TTL,
    ) {
    }

    public function create(int $moduleId, int $pageId, string $moduleKey, int|null $memberId): string
    {
        $payload = self::base64UrlEncode(json_encode([
            'mod' => $moduleId,
            'page' => $pageId,
            'key' => $moduleKey,
            'member' => $memberId,
            'exp' => $this->now() + $this->ttl,
            // Every token is unique, also if two are created in the same second
            'nonce' => bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.self::base64UrlEncode($this->sign($payload));
    }

    /**
     * @throws InvalidAccessTokenException
     */
    public function parse(string|null $token): AccessToken
    {
        if (null === $token || '' === $token) {
            throw new InvalidAccessTokenException('The access token is missing.');
        }

        $parts = explode('.', $token);

        if (2 !== \count($parts)) {
            throw new InvalidAccessTokenException('The access token is malformed.');
        }

        [$payload, $signature] = $parts;

        if (!hash_equals($this->sign($payload), self::base64UrlDecode($signature))) {
            throw new InvalidAccessTokenException('The signature of the access token is invalid.');
        }

        try {
            $data = json_decode(self::base64UrlDecode($payload), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidAccessTokenException('The access token is malformed.');
        }

        if (!\is_array($data) || !\is_int($data['mod'] ?? null) || !\is_int($data['page'] ?? null) || !\is_string($data['key'] ?? null) || !\is_int($data['exp'] ?? null) || !(null === ($data['member'] ?? null) || \is_int($data['member']))) {
            throw new InvalidAccessTokenException('The access token is malformed.');
        }

        if ($data['exp'] < $this->now()) {
            throw new InvalidAccessTokenException('The access token has expired.');
        }

        return new AccessToken($data['mod'], $data['page'], $data['key'], $data['member'] ?? null, $data['exp']);
    }

    private function sign(string $payload): string
    {
        // Own key for this purpose, derived from the kernel secret
        return hash_hmac('sha256', $payload, hash_hmac('sha256', 'markocupic_resource_booking.api_access_token', $this->secret, true), true);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
