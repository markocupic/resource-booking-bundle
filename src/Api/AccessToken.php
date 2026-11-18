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

/**
 * Content of the access token of the week calendar API (see AccessTokenManager).
 *
 * The token is created when the week calendar module is rendered: at this moment
 * Contao has checked that the visitor may see the page and the module. It is created
 * anew with every page load and only valid for a limited time.
 */
final class AccessToken
{
    /**
     * @param int|null $memberId ID of the logged-in member when the token was created, null for guests
     */
    public function __construct(
        public readonly int $moduleId,
        public readonly int $pageId,
        public readonly string $moduleKey,
        public readonly int|null $memberId,
        public readonly int $expiresAt,
    ) {
    }
}
