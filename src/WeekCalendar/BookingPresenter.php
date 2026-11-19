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

namespace Markocupic\ResourceBookingBundle\WeekCalendar;

use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Converts a booking (tl_resource_booking) into the data that is sent to the
 * week calendar.
 *
 * Everything in the Ajax response is visible to every logged-in member (also in
 * the browser's dev tools). Therefore only explicitly permitted data leaves the
 * server: the fields in RbbConfig::RBB_ALWAYS_SENT_BOOKING_FIELDS, the member
 * fields as "bookedBy<Field>" and the booking fields as "booking<Field>" that are
 * selected in the module and permitted in the bundle configuration. Never the
 * original rows of tl_member or tl_resource_booking.
 */
class BookingPresenter
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param array<string, mixed>      $booking          Row of tl_resource_booking
     * @param array<string, mixed>|null $member           Row of tl_member (the booking person), null if the member does not exist anymore
     * @param int|null                  $loggedInUserId   ID of the logged-in member, null for guests
     * @param list<string>              $memberFields     Displayed member fields (DisplayedFields::getMemberFields())
     * @param list<string>              $bookingFields    Displayed booking fields (DisplayedFields::getBookingFields())
     * @param bool                      $slotIsCancelable The slot can be canceled (time, logged-in user)
     *
     * @return array<string, mixed>
     */
    public function present(array $booking, array|null $member, int|null $loggedInUserId, array $memberFields, array $bookingFields, bool $slotIsCancelable): array
    {
        $data = [];

        foreach (RbbConfig::RBB_ALWAYS_SENT_BOOKING_FIELDS as $field) {
            $data[$field] = $booking[$field] ?? null;
        }

        $data['id'] = (int) $data['id'];
        $data['pid'] = (int) $data['pid'];
        $data['itemsBooked'] = (int) $data['itemsBooked'];
        $data['bookingTime'] = (int) $data['bookingTime'];
        // The database may return "0" as string, which is true in JavaScript
        $data['confirmed'] = (bool) $data['confirmed'];

        // Booking of the logged-in member (marked with an icon, not only with a color)
        $isOwn = null !== $loggedInUserId && $loggedInUserId === (int) ($booking['member'] ?? 0);

        // Fallback, if the name of the booking person must not be displayed
        $data['bookedByFullname'] = StringUtil::decodeEntities($this->translator->trans('RBB.anonymous', [], 'contao_default'));
        $data['bookedByAnonymous'] = true;

        if (null !== $member) {
            // Data about the booking person (tl_member) as "bookedBy<Field>"
            foreach ($memberFields as $field) {
                $data['bookedBy'.ucfirst($field)] = StringUtil::decodeEntities((string) ($member[$field] ?? ''));
            }

            $showFullname = \in_array('firstname', $memberFields, true) && \in_array('lastname', $memberFields, true);

            // The logged-in member always sees their own name
            if ($showFullname || $isOwn) {
                $data['bookedByFullname'] = StringUtil::decodeEntities(($member['firstname'] ?? '').' '.($member['lastname'] ?? ''));
                $data['bookedByAnonymous'] = false;
            }
        }

        // Booking details (tl_resource_booking) as "booking<Field>"
        foreach ($bookingFields as $field) {
            $data['booking'.ucfirst($field)] = StringUtil::decodeEntities((string) ($booking[$field] ?? ''));
        }

        $data['isOwn'] = $isOwn;

        // A user may only cancel their own bookings (and only if the booking person still exists).
        $data['canCancel'] = $slotIsCancelable && $isOwn && null !== $member;

        return $data;
    }
}
