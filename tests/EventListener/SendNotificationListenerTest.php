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

namespace Markocupic\ResourceBookingBundle\Tests\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PostCancellingEvent;
use Markocupic\ResourceBookingBundle\EventListener\SendNotificationListener;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Notification\NotificationSender;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;
use Symfony\Component\Security\Core\User\UserInterface;

class SendNotificationListenerTest extends ContaoTestCase
{
    private const BOOKING = ['id' => 1, 'pid' => 3, 'title' => 'Mo, 16.11.2026: 08:00 - 08:45'];

    public function testSendsTheBookingNotificationInTheContextOfTheWeekCalendar(): void
    {
        $sender = $this->createMock(NotificationSender::class);
        $sender
            ->expects($this->once())
            ->method('send')
            ->with(BookingNotificationType::NAME, [self::BOOKING], ['id' => 5, 'firstname' => 'Max'], 'de-CH', [], NotificationSender::CONTEXT_WEEK_CALENDAR)
            ->willReturn(true)
        ;

        (new SendNotificationListener($this->getFramework(), $sender))->onPostBooking($this->getEvent(PostBookingEvent::class));
    }

    public function testSendsTheCancellationNotification(): void
    {
        $sender = $this->createMock(NotificationSender::class);
        $sender
            ->expects($this->once())
            ->method('send')
            ->with(CancellationNotificationType::NAME)
            ->willReturn(true)
        ;

        (new SendNotificationListener($this->getFramework(), $sender))->onPostCancelling($this->getEvent(PostCancellingEvent::class));
    }

    public function testSendsNothingWithoutFrontendUser(): void
    {
        $sender = $this->createMock(NotificationSender::class);
        $sender
            ->expects($this->never())
            ->method('send')
        ;

        (new SendNotificationListener($this->getFramework(), $sender))->onPostBooking($this->getEvent(PostBookingEvent::class, $this->createStub(UserInterface::class)));
    }

    private function getFramework(): ContaoFramework
    {
        $member = $this->createClassWithPropertiesStub(MemberModel::class, ['id' => 5, 'firstname' => 'Max']);
        $member
            ->method('row')
            ->willReturn(['id' => 5, 'firstname' => 'Max'])
        ;

        $memberAdapter = $this->createAdapterStub(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturnCallback(static fn (int $id) => 5 === $id ? $member : null)
        ;

        return $this->createContaoFrameworkStub([MemberModel::class => $memberAdapter]);
    }

    /**
     * @param class-string<PostBookingEvent|PostCancellingEvent> $eventClass
     */
    private function getEvent(string $eventClass, UserInterface|null $user = null): PostBookingEvent|PostCancellingEvent
    {
        $booking = $this->createClassWithPropertiesStub(ResourceBookingModel::class, self::BOOKING);
        $booking
            ->method('row')
            ->willReturn(self::BOOKING)
        ;

        $bookings = $this->createStub(Collection::class);
        $bookings
            ->method('count')
            ->willReturn(1)
        ;

        $bookings
            ->method('getModels')
            ->willReturn([$booking])
        ;

        $appState = new AppState('33_0', $this->createStub(ModuleModel::class), $this->createStub(PageModel::class), null, null, [], 0, 0, 0, 'de-CH');

        $event = $this->createStub($eventClass);
        $event
            ->method('getBookingCollection')
            ->willReturn($bookings)
        ;

        $event
            ->method('getUser')
            ->willReturn($user ?? $this->createClassWithPropertiesStub(FrontendUser::class, ['id' => 5]))
        ;

        $event
            ->method('getAppState')
            ->willReturn($appState)
        ;

        return $event;
    }
}
