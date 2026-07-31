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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingTimeSlot;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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

    public function testRemoveChildRecordsDoesNothingWithoutAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchFirstColumn')
        ;

        $connection
            ->expects($this->never())
            ->method('delete')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack());
        $dca->removeChildRecords($this->mockClassWithProperties(DataContainer::class, ['id' => 0]));
    }

    public function testRemoveChildRecordsDoesNothingWhenNoBookingsExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([])
        ;

        $connection
            ->expects($this->never())
            ->method('delete')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack());
        $dca->removeChildRecords($this->mockClassWithProperties(DataContainer::class, ['id' => 7]));
    }

    public function testRemoveChildRecordsDeletesBookingsAndAddsAMessage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchFirstColumn')
            ->willReturn([11, 12])
        ;

        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('tl_resource_booking', ['timeSlotId' => 7], [Types::INTEGER])
        ;

        $messageAdapter = $this->mockAdapter(['addInfo']);
        $messageAdapter
            ->expects($this->once())
            ->method('addInfo')
            ->with($this->stringContains('11,12'))
        ;

        $dca = new ResourceBookingTimeSlot(
            $this->framework([Message::class => $messageAdapter]),
            $connection,
            new RequestStack(),
        );

        $dca->removeChildRecords($this->mockClassWithProperties(DataContainer::class, ['id' => 7]));
    }

    public function testAdaptBookingStartAndEndTimeDoesNothingWithoutAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchAssociative')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack());
        $dca->adaptBookingStartAndEndTime($this->mockClassWithProperties(DataContainer::class, ['id' => 0]));
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

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack());
        $dca->adaptBookingStartAndEndTime($this->mockClassWithProperties(DataContainer::class, ['id' => 7]));
    }

    public function testAdaptBookingStartAndEndTimeReturnsWhenThereAreNoBookings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 7, 'startTime' => 28800, 'endTime' => 36000])
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $dca = new ResourceBookingTimeSlot($this->framework(), $connection, new RequestStack());
        $dca->adaptBookingStartAndEndTime($this->mockClassWithProperties(DataContainer::class, ['id' => 7]));
    }

    public function testAdaptBookingStartAndEndTimeRewritesBookingTimesAndAddsAMessage(): void
    {
        $captured = null;

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 7, 'startTime' => 28800, 'endTime' => 36000])
        ;

        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
            ['id' => 99, 'startTime' => 111, 'endTime' => 222],
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

        // Date::parse only supplies the date part (before the space); pin it deterministically.
        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturn('2024-03-15 00:00')
        ;

        $messageAdapter = $this->mockAdapter(['addInfo']);
        $messageAdapter
            ->expects($this->once())
            ->method('addInfo')
            ->with($this->stringContains('99'))
        ;

        $dca = new ResourceBookingTimeSlot(
            $this->framework([Date::class => $dateAdapter, Message::class => $messageAdapter]),
            $connection,
            new RequestStack(),
        );

        $dca->adaptBookingStartAndEndTime($this->mockClassWithProperties(DataContainer::class, ['id' => 7]));

        $this->assertSame('tl_resource_booking', $captured['table']);
        $this->assertSame(['id' => 99], $captured['criteria']);
        $this->assertSame(
            [
                // date part from Date::parse + new slot start/end times (08:00 / 10:00).
                'startTime' => strtotime('2024-03-15 08:00'),
                'endTime' => strtotime('2024-03-15 10:00'),
            ],
            $captured['set'],
        );
    }

    private function createInstance(RequestStack $requestStack): ResourceBookingTimeSlot
    {
        return new ResourceBookingTimeSlot($this->framework(), $this->createMock(Connection::class), $requestStack);
    }

    /**
     * Builds a framework whose UtcTimeHelper adapter delegates to the real (pure)
     * helper, so time conversions stay deterministic. Extra adapters can be added.
     *
     * @param array<class-string, object> $extraAdapters
     */
    private function framework(array $extraAdapters = []): ContaoFramework
    {
        $utcAdapter = $this->mockAdapter(['parseStartTime', 'parseEndTime', 'strToTime']);
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

        return $this->mockContaoFramework(array_merge([UtcTimeHelper::class => $utcAdapter], $extraAdapters));
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
