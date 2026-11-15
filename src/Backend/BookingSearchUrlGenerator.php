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

namespace Markocupic\ResourceBookingBundle\Backend;

use Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\AddBookingSearchToWelcomeScreenListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Link to the booking search on the back end start page, e.g. for notifications:
 * https://example.com/contao?rbb_booking_uuid=<booking uuid>.
 */
class BookingSearchUrlGenerator
{
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    /**
     * Returns an empty string if the value is not a booking uuid.
     */
    public function generate(string $bookingUuid, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        if (null === BookingSearch::normalize($bookingUuid)) {
            return '';
        }

        return $this->router->generate('contao_backend', [AddBookingSearchToWelcomeScreenListener::QUERY_PARAMETER => trim($bookingUuid)], $referenceType);
    }
}
