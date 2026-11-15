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

namespace Markocupic\ResourceBookingBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before a booking or cancellation notification is sent
 * (see Notification\NotificationSender).
 *
 * Listeners can cancel the notification, choose another notification or
 * change the tokens and the language, e.g. depending on the context: the
 * week calendar (a logged-in member booked or cancelled) or a context of an
 * extension that sends the notification itself.
 */
class PrepareNotificationEvent extends Event
{
    private bool $cancelled = false;

    /**
     * @param array<string, string>      $tokens
     * @param list<array<string, mixed>> $bookings     rows of tl_resource_booking
     * @param array<string, mixed>       $resource     row of tl_resource_booking_resource
     * @param array<string, mixed>       $resourceType row of tl_resource_booking_resource_type
     * @param array<string, mixed>       $member       row of tl_member (empty without a member, e.g. a guest)
     */
    public function __construct(
        private readonly string $notificationType,
        private readonly string $context,
        private int $notificationId,
        private array $tokens,
        private string|null $locale,
        private readonly array $bookings,
        private readonly array $resource,
        private readonly array $resourceType,
        private readonly array $member,
    ) {
    }

    /**
     * BookingNotificationType::NAME or CancellationNotificationType::NAME.
     */
    public function getNotificationType(): string
    {
        return $this->notificationType;
    }

    /**
     * NotificationSender::CONTEXT_WEEK_CALENDAR or the context of an extension.
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * ID of the notification selected in the resource (0 = none).
     */
    public function getNotificationId(): int
    {
        return $this->notificationId;
    }

    public function setNotificationId(int $notificationId): self
    {
        $this->notificationId = $notificationId;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @param array<string, string> $tokens
     */
    public function setTokens(array $tokens): self
    {
        $this->tokens = $tokens;

        return $this;
    }

    public function setToken(string $name, string $value): self
    {
        $this->tokens[$name] = $value;

        return $this;
    }

    public function getLocale(): string|null
    {
        return $this->locale;
    }

    public function setLocale(string|null $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBookings(): array
    {
        return $this->bookings;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResource(): array
    {
        return $this->resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResourceType(): array
    {
        return $this->resourceType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMember(): array
    {
        return $this->member;
    }

    /**
     * The notification is not sent.
     */
    public function cancel(): self
    {
        $this->cancelled = true;
        $this->stopPropagation();

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
