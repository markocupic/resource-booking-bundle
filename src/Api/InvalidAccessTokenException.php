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

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * The access token is missing, manipulated or expired (HTTP 401): the page has to be
 * reloaded to get a new token.
 */
class InvalidAccessTokenException extends UnauthorizedHttpException
{
    public function __construct(string $message)
    {
        parent::__construct('RbbAccessToken', $message);
    }
}
