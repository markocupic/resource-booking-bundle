<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AppInitialization\Helper;

use Ramsey\Uuid\Uuid;

/**
 * The token is necessary to run multiple rbb applications on the same page
 * and should always be sent as a query parameter on every xhr request.
 *
 * The session data of each rbb instance is stored under $_SESSION[_resource_booking_bundle_attributes][$sessionId.'_'.$userId.'_'.$moduleKey.'_'.$token]
 *
 * Do only run once TokenManager::generateToken() per module instance;
 */
class TokenManager
{
    private static string|null $token = null;

    public static function generateToken(): void
    {
        static::$token = Uuid::uuid4()->toString();
    }

    public static function setToken(string $token): void
    {
        static::$token = $token;
    }

    /**
     * @throws \Exception
     */
    public static function getToken(): string
    {
        if (null === static::$token) {
            throw new \Exception('Token not set. Please use TokenManager::generateToken() first.');
        }

        return static::$token;
    }
}
