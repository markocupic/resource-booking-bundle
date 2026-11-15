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

namespace Markocupic\ResourceBookingBundle\Booking;

/**
 * Where a booking has been entered (tl_resource_booking.bookingSource).
 *
 * Extensions with their own booking process use their own value, e.g.
 * "hochfelden_booking_form", and add a label in
 * $GLOBALS['TL_LANG']['tl_resource_booking']['bookingSources'][<value>].
 * An empty value means unknown (bookings created before version 6).
 */
final class BookingSource
{
    /**
     * Booked by a logged-in member in the week calendar (front end module).
     */
    public const string WEEK_CALENDAR = 'week_calendar';

    /**
     * Created in the back end.
     */
    public const string BACKEND = 'backend';

    private function __construct()
    {
    }
}
