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

namespace Markocupic\ResourceBookingBundle\Tests\Notification;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Event\PrepareNotificationEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Notification\NotificationSender;
use Markocupic\ResourceBookingBundle\Notification\TokenBuilder;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class NotificationSenderTest extends ContaoTestCase
{
    private const BOOKING = ['id' => 1, 'pid' => 3, 'title' => 'Mo, 16.11.2026: 08:00 - 08:45', 'bookingUuid' => 'abc'];

    public function testSendsTheNotificationOfTheResource(): void
    {
        $notificationCenter = $this->getNotificationCenter();
        $notificationCenter
            ->expects($this->once())
            ->method('sendNotification')
            ->with(
                11,
                $this->callback(static fn (array $tokens): bool => 'Mo, 16.11.2026: 08:00 - 08:45'."\r\n" === $tokens['booking_details'] && 'Max' === $tokens['booking_person_firstname'] && '150' === $tokens['booking_costs']),
                'de-CH',
            )
        ;

        $sender = $this->getSender($notificationCenter);

        $this->assertTrue($sender->send(BookingNotificationType::NAME, [self::BOOKING], ['firstname' => 'Max'], 'de-CH', ['booking_costs' => 150]));
    }

    public function testSendsTheCancellationNotificationOfTheResource(): void
    {
        $notificationCenter = $this->getNotificationCenter();
        $notificationCenter
            ->expects($this->once())
            ->method('sendNotification')
            ->with(12, $this->anything(), null)
        ;

        $this->assertTrue($this->getSender($notificationCenter)->send(CancellationNotificationType::NAME, [self::BOOKING]));
    }

    public function testSendsNothingIfTheResourceHasNoNotification(): void
    {
        $notificationCenter = $this->getNotificationCenter();
        $notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $this->assertFalse($this->getSender($notificationCenter, bookingNotification: 0)->send(BookingNotificationType::NAME, [self::BOOKING]));
    }

    public function testSendsNothingWithoutBookingsOrResource(): void
    {
        $notificationCenter = $this->getNotificationCenter();
        $notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $sender = $this->getSender($notificationCenter);

        $this->assertFalse($sender->send(BookingNotificationType::NAME, []));
        $this->assertFalse($sender->send(BookingNotificationType::NAME, [['id' => 1, 'pid' => 99]]));
    }

    public function testThrowsForAnUnknownNotificationType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->getSender($this->getNotificationCenter())->send('foo', [self::BOOKING]);
    }

    public function testListenersCanCancelTheNotification(): void
    {
        $notificationCenter = $this->getNotificationCenter();
        $notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            PrepareNotificationEvent::class,
            static function (PrepareNotificationEvent $event): void {
                if (NotificationSender::CONTEXT_WEEK_CALENDAR === $event->getContext()) {
                    $event->cancel();
                }
            }
        );

        $this->assertFalse($this->getSender($notificationCenter, eventDispatcher: $eventDispatcher)->send(BookingNotificationType::NAME, [self::BOOKING], ['firstname' => 'Max']));
    }

    public function testListenersCanChangeTheTokensTheNotificationAndTheLocale(): void
    {
        $notificationCenter = $this->getNotificationCenter([11 => 'A', 21 => 'B']);
        $notificationCenter
            ->expects($this->once())
            ->method('sendNotification')
            ->with(
                21,
                $this->callback(static fn (array $tokens): bool => 'yes' === $tokens['custom']),
                'en',
            )
        ;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            PrepareNotificationEvent::class,
            static function (PrepareNotificationEvent $event): void {
                TestCase::assertSame('guest_form', $event->getContext());
                TestCase::assertSame(BookingNotificationType::NAME, $event->getNotificationType());
                TestCase::assertSame(11, $event->getNotificationId());
                TestCase::assertSame('Werkraum', $event->getResource()['title']);
                TestCase::assertSame([self::BOOKING], $event->getBookings());
                TestCase::assertSame([], $event->getMember());

                $event->setNotificationId(21)->setToken('custom', 'yes')->setLocale('en');
            }
        );

        $this->assertTrue($this->getSender($notificationCenter, eventDispatcher: $eventDispatcher)->send(BookingNotificationType::NAME, [self::BOOKING], [], null, [], 'guest_form'));
    }

    public function testTheContextIsTheSourceOfTheBookingByDefault(): void
    {
        $contexts = [];

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            PrepareNotificationEvent::class,
            static function (PrepareNotificationEvent $event) use (&$contexts): void {
                $contexts[] = $event->getContext();
            }
        );

        $sender = $this->getSender($this->getNotificationCenter(), eventDispatcher: $eventDispatcher);
        $sender->send(BookingNotificationType::NAME, [[...self::BOOKING, 'bookingSource' => 'guest_form']]);
        $sender->send(BookingNotificationType::NAME, [self::BOOKING]);
        $sender->send(BookingNotificationType::NAME, [[...self::BOOKING, 'bookingSource' => 'guest_form']], [], null, [], 'explicit');

        $this->assertSame(['guest_form', NotificationSender::CONTEXT_WEEK_CALENDAR, 'explicit'], $contexts);
    }

    public function testDoesNotSendANotificationOfAnotherType(): void
    {
        $notificationCenter = $this->getNotificationCenter([]);
        $notificationCenter
            ->expects($this->never())
            ->method('sendNotification')
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('is not of type'))
        ;

        $this->assertFalse($this->getSender($notificationCenter, logger: $logger)->send(BookingNotificationType::NAME, [self::BOOKING]));
    }

    public function testAFailedNotificationIsLoggedAndDoesNotThrow(): void
    {
        $notificationCenter = $this->getNotificationCenter();
        $notificationCenter
            ->method('sendNotification')
            ->willThrowException(new \RuntimeException('Mailer down'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Mailer down'))
        ;

        $this->assertFalse($this->getSender($notificationCenter, logger: $logger)->send(BookingNotificationType::NAME, [self::BOOKING]));
    }

    /**
     * @param array<int, string>|null $notifications notifications of the requested type (id => title)
     */
    private function getNotificationCenter(array|null $notifications = null): NotificationCenter&MockObject
    {
        $notificationCenter = $this->createMock(NotificationCenter::class);
        $notificationCenter
            ->method('getNotificationsForNotificationType')
            ->willReturnCallback(static fn (string $type): array => $notifications ?? (BookingNotificationType::NAME === $type ? [11 => 'Booking'] : [12 => 'Cancellation']))
        ;

        return $notificationCenter;
    }

    private function getSender(NotificationCenter $notificationCenter, int $bookingNotification = 11, EventDispatcher|null $eventDispatcher = null, LoggerInterface|null $logger = null): NotificationSender
    {
        return new NotificationSender($this->getFramework($bookingNotification), $eventDispatcher ?? new EventDispatcher(), $notificationCenter, new TokenBuilder(), $logger);
    }

    private function getFramework(int $bookingNotification): ContaoFramework
    {
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, [
            'id' => 3,
            'pid' => 4,
            'title' => 'Werkraum',
            'bookingNotification' => $bookingNotification,
            'cancellationNotification' => 12,
        ]);

        $resource
            ->method('row')
            ->willReturn(['id' => 3, 'pid' => 4, 'title' => 'Werkraum', 'bookingNotification' => $bookingNotification, 'cancellationNotification' => 12])
        ;

        $resourceType = $this->createClassWithPropertiesStub(ResourceBookingResourceTypeModel::class, ['id' => 4, 'title' => 'Räume']);
        $resourceType
            ->method('row')
            ->willReturn(['id' => 4, 'title' => 'Räume'])
        ;

        $resourceAdapter = $this->createAdapterStub(['findById']);
        $resourceAdapter
            ->method('findById')
            ->willReturnCallback(static fn (int $id) => 3 === $id ? $resource : null)
        ;

        $resourceTypeAdapter = $this->createAdapterStub(['findById']);
        $resourceTypeAdapter
            ->method('findById')
            ->willReturnCallback(static fn (int $id) => 4 === $id ? $resourceType : null)
        ;

        return $this->createContaoFrameworkStub([
            ResourceBookingResourceModel::class => $resourceAdapter,
            ResourceBookingResourceTypeModel::class => $resourceTypeAdapter,
            Config::class => $this->createConfiguredAdapterStub(['get' => 'd.m.Y H:i']),
        ]);
    }
}
