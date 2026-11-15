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

namespace Markocupic\ResourceBookingBundle\Tests\WeekCalendar;

use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Slot\SlotMain;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Markocupic\ResourceBookingBundle\WeekCalendar\BookingPresenter;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekTableBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WeekTableBuilderTest extends ContaoTestCase
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function testWeekdaysWithoutAnyTimeSlotAreHidden(): void
    {
        $timeSlots = [
            ['id' => 1, 'weekdays' => serialize(['monday', 'tuesday'])],
            ['id' => 2, 'weekdays' => serialize(['friday'])],
        ];

        $this->assertSame(['monday', 'tuesday', 'friday'], WeekTableBuilder::getVisibleWeekdays($timeSlots, self::DAYS));
    }

    public function testAllWeekdaysAreDisplayedWithoutTimeSlots(): void
    {
        $this->assertSame(self::DAYS, WeekTableBuilder::getVisibleWeekdays([], self::DAYS));
    }

    public function testTimeSlotsWithoutAnyDisplayedWeekdayAreHidden(): void
    {
        $this->assertTrue(WeekTableBuilder::isTimeSlotDisplayed(['weekdays' => serialize(['friday'])], ['monday', 'friday']));
        $this->assertFalse(WeekTableBuilder::isTimeSlotDisplayed(['weekdays' => serialize(['sunday'])], ['monday', 'friday']));
        // A time slot without weekdays is available on every weekday
        $this->assertTrue(WeekTableBuilder::isTimeSlotDisplayed(['weekdays' => null], ['monday']));
    }

    public function testRowAttributes(): void
    {
        $row = WeekTableBuilder::getRowAttributes(5, ['id' => 12, 'cssID' => null]);
        $this->assertSame('timeSlotModId_5_12', $row->cssRowId);
        $this->assertSame('rbb-time-slot-12', $row->cssRowClass);

        // The CSS ID of the time slot overrides the generated one
        $row = WeekTableBuilder::getRowAttributes(5, ['id' => 12, 'cssID' => serialize(['morning', 'my-class'])]);
        $this->assertSame('morning', $row->cssRowId);
    }

    public function testCheckboxIdAndValue(): void
    {
        $this->assertSame('bookingCheckbox_modId_5_1_3', WeekTableBuilder::getBookingCheckboxId(5, 1, 3));
        $this->assertSame('12-1000-2000-500', WeekTableBuilder::getBookingCheckboxValue(12, 1000, 2000, 500));
    }

    public function testBuildRows(): void
    {
        $week = strtotime('2026-11-16 00:00:00');

        $timeSlots = [
            $this->timeSlot(['id' => 1, 'startTime' => 8 * 3600, 'endTime' => 9 * 3600, 'weekdays' => serialize(['monday', 'tuesday'])]),
            // Only available on sunday: sunday becomes a displayed weekday (column)
            $this->timeSlot(['id' => 2, 'startTime' => 10 * 3600, 'endTime' => 11 * 3600, 'weekdays' => serialize(['sunday'])]),
        ];

        $timeSlotAdapter = $this->createAdapterStub(['findPublishedByPid']);
        $timeSlotAdapter
            ->method('findPublishedByPid')
            ->willReturn($timeSlots)
        ;

        $memberAdapter = $this->createAdapterStub(['findById']);
        $memberAdapter
            ->method('findById')
            ->willReturn(null)
        ;

        $framework = $this->createContaoFrameworkStub([
            ResourceBookingTimeSlotModel::class => $timeSlotAdapter,
            MemberModel::class => $memberAdapter,
        ]);

        // Monday 08:00 is booked by member 7
        $booking = ['id' => 30, 'pid' => 3, 'member' => 7, 'itemsBooked' => 1, 'bookingTime' => 1, 'confirmed' => 1];

        $slotFactory = $this->createMock(SlotFactory::class);
        $slotFactory
            ->expects($this->once())
            ->method('preloadBookings')
            ->with(3, $week, strtotime('+8 days', $week))
        ;

        $slotFactory
            ->method('get')
            ->willReturnCallback(
                function (int $timeSlotId, string $mode, ResourceBookingResourceModel $resource, int $startTime, int $endTime) use ($week, $booking, $framework): SlotMain {
                    $slot = new SlotMain($framework, $this->createStub(TokenStorageInterface::class), $this->createStub(Utils::class));
                    $isMonday = $startTime === $week + 8 * 3600;
                    $slot->setRow(['timeSlotId' => $timeSlotId, 'startTime' => $startTime, 'endTime' => $endTime, 'hasBookings' => $isMonday, 'bookings' => $isMonday ? [$booking] : [], 'resource' => ['itemsAvailable' => 1]]);

                    return $slot;
                },
            )
        ;

        $utils = $this->createStub(Utils::class);
        $utils
            ->method('getDisplayedMemberFields')
            ->willReturn(['firstname', 'lastname'])
        ;

        $utils
            ->method('getDisplayedBookingFields')
            ->willReturn([])
        ;

        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('Anonym')
        ;

        $builder = new WeekTableBuilder($framework, $slotFactory, new BookingPresenter($translator), $utils);

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 5]);
        $resource = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 3, 'timeSlotType' => 2]);

        $rows = $builder->buildRows(self::DAYS, $week, $module, $resource);

        // Two rows (one per time slot), three columns (monday, tuesday and sunday);
        // wednesday to saturday are hidden, because no time slot is available on these days.
        $this->assertCount(2, $rows);
        $this->assertSame('timeSlotModId_5_1', $rows[0]['rowData']->cssRowId);
        $this->assertSame('timeSlotModId_5_2', $rows[1]['rowData']->cssRowId);

        foreach ($rows as $row) {
            $this->assertSame([0, 1, 6], array_column($row['cellData'], 'index'));
        }

        [$monday, $tuesday] = $rows[0]['cellData'];

        $this->assertSame('bookingCheckbox_modId_5_0_1', $tuesday['bookingCheckboxId']);
        $this->assertSame(\sprintf('1-%s-%s-%s', $week + 86400 + 8 * 3600, $week + 86400 + 9 * 3600, $week), $tuesday['bookingCheckboxValue']);

        // The booking is passed through the presenter: no original row of tl_resource_booking
        $this->assertSame(30, $monday['bookings'][0]['id']);
        $this->assertArrayNotHasKey('member', $monday['bookings'][0]);
        $this->assertSame('Anonym', $monday['bookings'][0]['bookedByFullname']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function timeSlot(array $row): ResourceBookingTimeSlotModel
    {
        $timeSlot = $this->createStub(ResourceBookingTimeSlotModel::class);
        $timeSlot
            ->method('row')
            ->willReturn($row)
        ;

        return $timeSlot;
    }
}
