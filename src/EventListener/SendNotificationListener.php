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

namespace Markocupic\ResourceBookingBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\Notification\NotificationSender;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Week calendar: sends the notification selected in the resource after a member
 * has booked or cancelled.
 */
class SendNotificationListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly NotificationSender $notificationSender,
    ) {
    }

    #[AsEventListener]
    public function onPostBooking(PostBookingEvent $event): void
    {
        $this->send(BookingNotificationType::NAME, $event->getBookingCollection(), $event->getUser(), $event->getAppState()->language);
    }

    #[AsEventListener]
    public function onPostCancelling(PostCancellingEvent $event): void
    {
        $this->send(CancellationNotificationType::NAME, $event->getBookingCollection(), $event->getUser(), $event->getAppState()->language);
    }

    private function send(string $notificationType, Collection|null $bookings, UserInterface $user, string $locale): void
    {
        if (!$user instanceof FrontendUser || null === $bookings || 0 === $bookings->count()) {
            return;
        }

        $member = $this->framework->getAdapter(MemberModel::class)->findById((int) $user->id);

        if (null === $member) {
            return;
        }

        $this->notificationSender->send(
            $notificationType,
            array_map(static fn ($booking): array => $booking->row(), $bookings->getModels()),
            $member->row(),
            $locale,
            [],
            NotificationSender::CONTEXT_WEEK_CALENDAR,
        );
    }
}
