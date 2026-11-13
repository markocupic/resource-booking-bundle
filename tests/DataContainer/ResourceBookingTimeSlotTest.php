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

namespace Markocupic\ResourceBookingBundle\Tests\DataContainer;

use Contao\Config;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingTimeSlot;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingTimeSlotTest extends ContaoTestCase
{
    public function testChildRecordCallbackFormatsTheTimeSpan(): void
    {
        $html = $this->createInstance(new RequestStack())->childRecordCallback([
            'title' => 'Morning',
            'startTime' => 28800, // 08:00
            'endTime' => 36000, // 10:00
        ]);

        $this->assertStringContainsString('Morning', $html);
        $this->assertStringContainsString('08:00-10:00', $html);
    }

    public function testLoadStartTime(): void
    {
        $dca = $this->createInstance(new RequestStack());

        $this->assertSame('08:00', $dca->loadStartTime(28800));
        $this->assertSame('', $dca->loadStartTime(-1));
    }

    public function testLoadEndTime(): void
    {
        $dca = $this->createInstance(new RequestStack());

        $this->assertSame('24:00', $dca->loadEndTime(86400));
        $this->assertSame('', $dca->loadEndTime(-5));
    }

    #[DataProvider('provideStartTimes')]
    public function testSetCorrectStartTime(string $input, int $expected): void
    {
        // A request must be present: the invalid branch writes "00:00" back to it.
        $dca = $this->createInstance($this->requestStackWithPost([]));

        $this->assertSame($expected, $dca->setCorrectStartTime($input, $this->createMock(DataContainer::class)));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function provideStartTimes(): iterable
    {
        return [
            'valid time' => ['09:30', 34200],
            'midnight is timestamp 0' => ['00:00', 0],
            'last minute of the day' => ['23:59', 86340],
            // The start-time regex only allows 00:00–23:59, so 24:00 falls back to 0.
            '24:00 falls back to 0' => ['24:00', 0],
            'single digit hour is invalid' => ['9:30', 0],
            'garbage is invalid' => ['not-a-time', 0],
        ];
    }

    public function testSetCorrectEndTimeKeepsAnEndAfterTheStart(): void
    {
        $dca = $this->createInstance($this->requestStackWithPost(['startTime' => '08:00']));

        $this->assertSame(36000, $dca->setCorrectEndTime('10:00', $this->createMock(DataContainer::class)));
    }

    public function testSetCorrectEndTimeNormalizesMidnightToEndOfDay(): void
    {
        $dca = $this->createInstance($this->requestStackWithPost(['startTime' => '08:00']));

        $this->assertSame(86400, $dca->setCorrectEndTime('00:00', $this->createMock(DataContainer::class)));
    }

    public function testSetCorrectEndTimeBumpsEndByOneMinuteWhenNotAfterStart(): void
    {
        $dca = $this->createInstance($this->requestStackWithPost(['startTime' => '10:00']));

        $this->assertSame(36060, $dca->setCorrectEndTime('09:00', $this->createMock(DataContainer::class)));
    }

    public function testDeletionIsAllowedWithoutAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack(), $this->translator());
        $dca->preventDeletionWithFutureBookings($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 0]));
    }

    public function testDeletionIsAllowedWithoutFutureBookings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('endTime > ?'))
            ->willReturn(0)
        ;

        // Past bookings are never deleted
        $connection
            ->expects($this->never())
            ->method('delete')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack(), $this->translator());
        $dca->preventDeletionWithFutureBookings($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]));
    }

    public function testDeletionIsBlockedWithFutureBookings(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(3)
        ;

        $messageAdapter = $this->createAdapterMock(['addError']);
        $messageAdapter
            ->expects($this->once())
            ->method('addError')
            ->with('RBB.BE.deleteTimeSlotBlocked:3')
        ;

        $systemAdapter = $this->createConfiguredAdapterStub(['getReferer' => 'https://example.com/contao']);

        $dca = new ResourceBookingTimeSlot(
            $this->framework([Message::class => $messageAdapter, System::class => $systemAdapter]),
            $connection,
            new RequestStack(),
            $this->translator(),
        );

        $this->expectException(RedirectResponseException::class);

        $dca->preventDeletionWithFutureBookings($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]));
    }

    public function testAdaptBookingStartAndEndTimeDoesNothingWithoutAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAssociative')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack(), $this->translator());
        $dca->adaptBookingStartAndEndTime($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 0]));
    }

    public function testAdaptBookingStartAndEndTimeReturnsWhenTheSlotIsMissing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(false)
        ;

        $connection
            ->expects($this->never())
            ->method('fetchAllAssociative')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack(), $this->translator());
        $dca->adaptBookingStartAndEndTime($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]));
    }

    public function testAdaptBookingStartAndEndTimeOnlyLoadsFutureBookings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 7, 'startTime' => 28800, 'endTime' => 36000])
        ;

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('startTime > ?'))
            ->willReturn([])
        ;

        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack(), $this->translator());
        $dca->adaptBookingStartAndEndTime($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]));
    }

    public function testAdaptBookingStartAndEndTimeRewritesTimesAndTitle(): void
    {
        $captured = null;
        $oldStart = strtotime('2030-03-15 07:30');
        $oldEnd = strtotime('2030-03-15 08:15');

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 7, 'startTime' => 28800, 'endTime' => 36000])
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 99, 'startTime' => $oldStart, 'endTime' => $oldEnd, 'title' => 'Room : booked by John Doe [15.03.2030 07:30 - 15.03.2030 08:15]'],
            ])
        ;

        $connection
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(
                static function (string $table, array $set, array $criteria) use (&$captured): int {
                    $captured = ['table' => $table, 'set' => $set, 'criteria' => $criteria];

                    return 1;
                },
            )
        ;

        $dateAdapter = $this->createAdapterStub(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => date('' === $format ? 'd.m.Y H:i' : $format, $tstamp))
        ;

        $configAdapter = $this->createConfiguredAdapterStub(['get' => 'd.m.Y H:i']);

        $messageAdapter = $this->createAdapterMock(['addInfo']);
        $messageAdapter
            ->expects($this->once())
            ->method('addInfo')
            ->with('RBB.BE.bookingsAdapted:1')
        ;

        $dca = new ResourceBookingTimeSlot(
            $this->framework([Date::class => $dateAdapter, Config::class => $configAdapter, Message::class => $messageAdapter]),
            $connection,
            new RequestStack(),
            $this->translator(),
        );

        $dca->adaptBookingStartAndEndTime($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]));

        $this->assertSame('tl_resource_booking', $captured['table']);
        $this->assertSame(['id' => 99], $captured['criteria']);
        // Date of the booking + new slot start/end times (08:00 / 10:00)
        $this->assertSame(strtotime('2030-03-15 08:00'), $captured['set']['startTime']);
        $this->assertSame(strtotime('2030-03-15 10:00'), $captured['set']['endTime']);
        $this->assertSame('Room : booked by John Doe [15.03.2030 08:00 - 15.03.2030 10:00]', $captured['set']['title']);
    }

    public function testAdaptBookingStartAndEndTimeSkipsUnchangedBookings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 7, 'startTime' => 28800, 'endTime' => 36000])
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 99, 'startTime' => strtotime('2030-03-15 08:00'), 'endTime' => strtotime('2030-03-15 10:00'), 'title' => ''],
            ])
        ;

        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $dateAdapter = $this->createAdapterStub(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => date('' === $format ? 'd.m.Y H:i' : $format, $tstamp))
        ;

        $dca = new ResourceBookingTimeSlot($this->framework([Date::class => $dateAdapter]), $connection, new RequestStack(), $this->translator());
        $dca->adaptBookingStartAndEndTime($this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]));
    }

    private function createInstance(RequestStack $requestStack): ResourceBookingTimeSlot
    {
        return new ResourceBookingTimeSlot($this->framework(), $this->createStub(Connection::class), $requestStack, $this->translator());
    }

    /**
     * Returns "key:param1,param2" so the messages can be asserted.
     */
    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id, array $parameters = []): string => $id.($parameters ? ':'.implode(',', $parameters) : ''))
        ;

        return $translator;
    }

    /**
     * Builds a framework whose UtcTimeHelper adapter delegates to the real (pure)
     * helper, so time conversions stay deterministic. Extra adapters can be added.
     *
     * @param array<class-string, object> $extraAdapters
     */
    private function framework(array $extraAdapters = []): ContaoFramework
    {
        $utcAdapter = $this->createAdapterMock(['parseStartTime', 'parseEndTime', 'strToTime']);
        $utcAdapter
            ->method('parseStartTime')
            ->willReturnCallback(static fn (int $t): string => UtcTimeHelper::parseStartTime($t))
        ;

        $utcAdapter
            ->method('parseEndTime')
            ->willReturnCallback(static fn (int $t): string => UtcTimeHelper::parseEndTime($t))
        ;

        $utcAdapter
            ->method('strToTime')
            ->willReturnCallback(static fn (string $s): int => UtcTimeHelper::strToTime($s))
        ;

        return $this->createContaoFrameworkMock(array_merge([UtcTimeHelper::class => $utcAdapter], $extraAdapters));
    }

    /**
     * @param array<string, mixed> $post
     */
    private function requestStackWithPost(array $post): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/contao', 'POST', $post));

        return $requestStack;
    }
}
