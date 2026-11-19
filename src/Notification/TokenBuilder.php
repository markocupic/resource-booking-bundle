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

namespace Markocupic\ResourceBookingBundle\Notification;

use Contao\StringUtil;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Builds the notification tokens from the booking person, the bookings,
 * the resource and the resource type.
 */
class TokenBuilder
{
    public function __construct(private readonly ClockInterface $clock = new NativeClock())
    {
    }

    /**
     * @param array<string, mixed>       $member       row of tl_member
     * @param list<array<string, mixed>> $bookings     rows of tl_resource_booking
     * @param array<string, mixed>       $resource     row of tl_resource_booking_resource
     * @param array<string, mixed>       $resourceType row of tl_resource_booking_resource_type
     *
     * @return array<string, string>
     */
    public function build(array $member, array $bookings, array $resource, array $resourceType, string $datimFormat, int|null $time = null): array
    {
        $tokens = [];

        // Tokens about the booking person (tl_member)
        foreach ($member as $key => $value) {
            if ('password' === $key) {
                continue;
            }

            $tokens['booking_person_'.$key] = $this->getCleanedValue($value);
        }

        // Tokens about the first booking (tl_resource_booking)
        foreach ($bookings[0] ?? [] as $key => $value) {
            $tokens['booking_'.$key] = $this->getCleanedValue($value);
        }

        // The title of the first booking is not meaningful, all bookings are listed in "booking_details"
        unset($tokens['booking_title']);

        $details = '';

        foreach ($bookings as $booking) {
            $details .= ($booking['title'] ?? '')."\r\n";
        }

        $tokens['booking_details'] = $details;
        $tokens['booking_details_html'] = nl2br($details);
        $tokens['booking_datim'] = date($datimFormat, $time ?? $this->clock->now()->getTimestamp());

        // Tokens about the resource (tl_resource_booking_resource)
        foreach ($resource as $key => $value) {
            $tokens['booking_resource_'.$key] = $this->getCleanedValue($value);
        }

        // Tokens about the resource type (tl_resource_booking_resource_type)
        foreach ($resourceType as $key => $value) {
            $tokens['booking_resource_type_'.$key] = $this->getCleanedValue($value);
        }

        return $tokens;
    }

    /**
     * Converts binary values (UUIDs) to strings, because binary values
     * cannot be used as tokens in the Notification Center.
     */
    private function getCleanedValue(mixed $value): string
    {
        if (null === $value || '' === $value || false === $value) {
            return '';
        }

        if (\is_object($value) || \is_array($value)) {
            $value = serialize($value);
        }

        $value = (string) $value;

        if ($this->isBinary($value)) {
            return 16 === \strlen($value) ? StringUtil::binToUuid($value) : '';
        }

        $array = StringUtil::deserialize($value);

        if (!empty($array) && \is_array($array)) {
            return serialize(
                array_map(
                    fn ($item) => \is_string($item) && 16 === \strlen($item) && $this->isBinary($item) ? StringUtil::binToUuid($item) : $item,
                    $array,
                ),
            );
        }

        return $value;
    }

    /**
     * A value is binary if it is not valid UTF-8 or contains control characters.
     * Umlauts and other multibyte characters are not binary.
     */
    private function isBinary(string $value): bool
    {
        return !preg_match('//u', $value) || preg_match('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', $value) > 0;
    }
}
