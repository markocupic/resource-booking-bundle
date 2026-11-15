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

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\Booking\BookingSource;
use Markocupic\ResourceBookingBundle\Event\PrepareNotificationEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

/**
 * Sends the booking or cancellation notification selected in the resource
 * (tl_resource_booking_resource.bookingNotification/cancellationNotification).
 *
 * Used by the week calendar (SendNotificationListener) and by extensions with
 * their own booking process, e.g. a booking form for guests:
 *
 *   $sender->send(BookingNotificationType::NAME, $bookingRows, [], null, ['booking_costs' => '150'], 'my_guest_form');
 *
 * Before sending, the PrepareNotificationEvent is dispatched: listeners can cancel
 * the notification or change the tokens.
 */
class NotificationSender
{
    public const CONTEXT_WEEK_CALENDAR = BookingSource::WEEK_CALENDAR;

    /**
     * Notification type => field of tl_resource_booking_resource.
     */
    public const FIELDS = [
        BookingNotificationType::NAME => 'bookingNotification',
        CancellationNotificationType::NAME => 'cancellationNotification',
    ];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationCenter $notificationCenter,
        private readonly TokenBuilder $tokenBuilder,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Returns true if the notification has been sent. A failing notification is
     * logged and never throws: it must not roll back the booking or the cancellation.
     *
     * @param list<array<string, mixed>> $bookings rows of tl_resource_booking, all of the same resource
     * @param array<string, mixed>       $member   row of tl_member (empty array without a member)
     * @param array<string, mixed>       $tokens   additional tokens, they overwrite the tokens of the TokenBuilder
     * @param string|null                $context  default: the source of the first booking (tl_resource_booking.bookingSource)
     */
    public function send(string $notificationType, array $bookings, array $member = [], string|null $locale = null, array $tokens = [], string|null $context = null): bool
    {
        if (!isset(self::FIELDS[$notificationType])) {
            throw new \InvalidArgumentException(\sprintf('Unknown notification type "%s".', $notificationType));
        }

        if ([] === $bookings) {
            return false;
        }

        $context ??= (string) ($bookings[0]['bookingSource'] ?? '') ?: self::CONTEXT_WEEK_CALENDAR;

        // All bookings of one request belong to the same resource
        $resource = $this->framework->getAdapter(ResourceBookingResourceModel::class)->findById((int) ($bookings[0]['pid'] ?? 0));

        if (null === $resource) {
            return false;
        }

        $resourceType = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class)->findById((int) $resource->pid);
        $resourceTypeRow = null !== $resourceType ? $resourceType->row() : [];

        $tokens = [
            ...$this->tokenBuilder->build($member, $bookings, $resource->row(), $resourceTypeRow, (string) $this->framework->getAdapter(Config::class)->get('datimFormat')),
            ...array_map(strval(...), $tokens),
        ];

        $event = new PrepareNotificationEvent(
            $notificationType,
            $context,
            (int) $resource->{self::FIELDS[$notificationType]},
            $tokens,
            null !== $locale && '' !== $locale ? $locale : null,
            $bookings,
            $resource->row(),
            $resourceTypeRow,
            $member,
        );

        $this->eventDispatcher->dispatch($event);

        $notificationId = $event->getNotificationId();

        if ($event->isCancelled() || $notificationId < 1) {
            return false;
        }

        // Only notifications of the matching type, otherwise the Notification Center throws an exception
        if (!\array_key_exists($notificationId, $this->notificationCenter->getNotificationsForNotificationType($notificationType))) {
            $this->contaoErrorLogger?->error(\sprintf('Resource booking: The notification with ID %d is not of type "%s".', $notificationId, $notificationType));

            return false;
        }

        try {
            $this->notificationCenter->sendNotification($notificationId, $event->getTokens(), $event->getLocale());
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(\sprintf('Resource booking: Could not send notification with ID %d: %s', $notificationId, $e->getMessage()));

            return false;
        }

        return true;
    }
}
