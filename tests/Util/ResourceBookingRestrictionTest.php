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

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use PHPUnit\Framework\Attributes\DataProvider;

class ResourceBookingRestrictionTest extends ContaoTestCase
{
    public function testBeginWeekIsReadFromTheTimetable(): void
    {
        $this->assertSame('sunday', ResourceBookingRestriction::getBeginWeek(['beginWeek' => 'sunday']));
        $this->assertSame('saturday', ResourceBookingRestriction::getBeginWeek(['beginWeek' => ' Saturday ']));
        $this->assertSame('sunday', ResourceBookingRestriction::getBeginWeek($this->createClassWithPropertiesStub(ResourceBookingTimeSlotTypeModel::class, ['beginWeek' => 'sunday'])));
    }

    public function testBeginWeekFallsBackToMonday(): void
    {
        $this->assertSame('monday', ResourceBookingRestriction::getBeginWeek(null));
        $this->assertSame('monday', ResourceBookingRestriction::getBeginWeek([]));
        $this->assertSame('monday', ResourceBookingRestriction::getBeginWeek(['beginWeek' => null]));
        $this->assertSame('monday', ResourceBookingRestriction::getBeginWeek(['beginWeek' => 'someday']));
    }

    public function testBookableUntilIsNullIfDisabledOrEmpty(): void
    {
        $this->assertNull(ResourceBookingRestriction::getBookableUntil(null));
        $this->assertNull(ResourceBookingRestriction::getBookableUntil(['addBookableUntil' => '', 'bookableUntil' => '1700000000']));
        $this->assertNull(ResourceBookingRestriction::getBookableUntil(['addBookableUntil' => '1', 'bookableUntil' => '']));
    }

    public function testBookableUntilReturnsTimestamp(): void
    {
        $this->assertSame(1700000000, ResourceBookingRestriction::getBookableUntil(['addBookableUntil' => '1', 'bookableUntil' => '1700000000']));
    }

    public function testWeeksBackAndAheadFallBackToTheDefaults(): void
    {
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_BACK, ResourceBookingRestriction::getWeeksBack(null));
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_AHEAD, ResourceBookingRestriction::getWeeksAhead(null));
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_BACK, ResourceBookingRestriction::getWeeksBack(['weeksBack' => '']));
    }

    public function testWeeksBackAndAheadAreReadFromTheResource(): void
    {
        $row = ['weeksBack' => '0', 'weeksAhead' => '12'];

        $this->assertSame(0, ResourceBookingRestriction::getWeeksBack($row));
        $this->assertSame(12, ResourceBookingRestriction::getWeeksAhead($row));
        $this->assertSame(0, ResourceBookingRestriction::getWeeksAhead(['weeksAhead' => -3]));
    }

    public function testAutoConfirmIsEnabledByDefault(): void
    {
        $this->assertTrue(ResourceBookingRestriction::isAutoConfirmEnabled(null));
        $this->assertTrue(ResourceBookingRestriction::isAutoConfirmEnabled([]));
    }

    public function testAutoConfirmIsReadFromTheResource(): void
    {
        $this->assertTrue(ResourceBookingRestriction::isAutoConfirmEnabled(['autoConfirm' => '1']));
        $this->assertFalse(ResourceBookingRestriction::isAutoConfirmEnabled(['autoConfirm' => '']));
        $this->assertFalse(ResourceBookingRestriction::isAutoConfirmEnabled(['autoConfirm' => 0]));

        $model = $this->createClassWithPropertiesMock(ResourceBookingResourceModel::class, ['autoConfirm' => false]);
        $this->assertFalse(ResourceBookingRestriction::isAutoConfirmEnabled($model));
    }

    public function testTimeSlotWithoutWeekdaysIsAvailableOnEveryWeekday(): void
    {
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday(null, 'sunday'));
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday([], 'sunday'));
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday(['weekdays' => null], 'sunday'));
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday(['weekdays' => ''], 'sunday'));
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday(['weekdays' => serialize([])], 'sunday'));
    }

    public function testTimeSlotIsOnlyAvailableOnItsWeekdays(): void
    {
        $row = ['weekdays' => serialize(['monday', 'tuesday', 'thursday'])];

        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($row, 'monday'));
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($row, 'Thursday'));
        $this->assertFalse(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($row, 'wednesday'));

        $model = $this->createClassWithPropertiesStub(ResourceBookingTimeSlotModel::class, ['weekdays' => serialize(['friday'])]);
        $this->assertTrue(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($model, 'friday'));
        $this->assertFalse(ResourceBookingRestriction::isTimeSlotAvailableOnWeekday($model, 'monday'));
    }

    public function testBlockedPeriodCoversTheDaysBetweenDateFromAndDateTo(): void
    {
        $period = $this->blockedPeriod();
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
        // Last day is inclusive
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-18 16:00'), strtotime('2030-10-18 17:00')));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-19 08:00'), strtotime('2030-10-19 09:00')));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-06 08:00'), strtotime('2030-10-06 09:00')));
    }

    public function testInactiveBlockedPeriodBlocksNothing(): void
    {
        $period = $this->blockedPeriod(['published' => '']);

        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, ['id' => 3, 'pid' => 4], strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
    }

    public function testBlockedPeriodWithoutEndDateIsUnlimited(): void
    {
        $period = $this->blockedPeriod(['dateTo' => 0]);
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2045-06-01 08:00'), strtotime('2045-06-01 09:00')));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-06 08:00'), strtotime('2030-10-06 09:00')));
    }

    public function testBlockedTimesOnlyBlockTheirWeekdays(): void
    {
        // 2030-10-07 is a monday, 2030-10-12 a saturday
        $period = $this->blockedPeriod(['blockedTimes' => serialize([
            ['weekday' => 'saturday', 'timeFrom' => '', 'timeTo' => ''],
            ['weekday' => 'sunday', 'timeFrom' => '', 'timeTo' => ''],
        ])]);
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-12 08:00'), strtotime('2030-10-12 09:00')));
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-13 23:00'), strtotime('2030-10-14 00:00')));
    }

    public function testBlockedTimesWithTimesOnlyBlockOverlappingSlots(): void
    {
        $period = $this->blockedPeriod(['blockedTimes' => serialize([
            ['weekday' => 'monday', 'timeFrom' => '12:00', 'timeTo' => '18:00'],
            ['weekday' => 'friday', 'timeFrom' => '14:00', 'timeTo' => ''],
        ])]);
        $resource = ['id' => 3, 'pid' => 4];

        // Monday
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-07 11:00'), strtotime('2030-10-07 12:00')));
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-07 11:30'), strtotime('2030-10-07 12:15')));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-07 18:00'), strtotime('2030-10-07 19:00')));
        // Tuesday: no blocked time
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-08 13:00'), strtotime('2030-10-08 14:00')));
        // Friday from 14:00 until the end of the day
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-11 13:00'), strtotime('2030-10-11 14:00')));
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-11 20:00'), strtotime('2030-10-11 21:00')));
    }

    public function testDailyBlockedTimeAppliesToEveryDay(): void
    {
        $period = $this->blockedPeriod(['blockedTimes' => serialize([['weekday' => 'daily', 'timeFrom' => '12:00', 'timeTo' => '13:00']])]);
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-08 12:15'), strtotime('2030-10-08 12:45')));
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-12 12:15'), strtotime('2030-10-12 12:45')));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($period, $resource, strtotime('2030-10-12 13:00'), strtotime('2030-10-12 14:00')));
    }

    public function testInvalidBlockedTimeRowsAreIgnored(): void
    {
        $period = $this->blockedPeriod(['blockedTimes' => serialize([['weekday' => 'someday'], 'foo'])]);

        $this->assertSame([], ResourceBookingRestriction::getBlockedTimes($period));
    }

    public function testBlockedPeriodScope(): void
    {
        $start = strtotime('2030-10-07 08:00');
        $end = strtotime('2030-10-07 09:00');

        $byType = $this->blockedPeriod(['scope' => 'resourceTypes', 'resourceTypes' => serialize(['4'])]);
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($byType, ['id' => 3, 'pid' => 4], $start, $end));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($byType, ['id' => 3, 'pid' => 5], $start, $end));

        $byResource = $this->blockedPeriod(['scope' => 'resources', 'resources' => serialize(['3', '8'])]);
        $this->assertTrue(ResourceBookingRestriction::isInBlockedPeriod($byResource, ['id' => 8, 'pid' => 4], $start, $end));
        $this->assertFalse(ResourceBookingRestriction::isInBlockedPeriod($byResource, ['id' => 9, 'pid' => 4], $start, $end));
    }

    public function testValidTimeSlot(): void
    {
        [$timeSlot, $timeSlotType, $resource] = $this->timeSlotFixture();

        // 08:00 - 08:45 on the day of the slot
        $start = strtotime('2030-10-07 08:00');
        $end = strtotime('2030-10-07 08:45');

        $this->assertTrue(ResourceBookingRestriction::isValidTimeSlot($timeSlot, $timeSlotType, $resource, $start, $end));

        // Repetition one week later
        $this->assertTrue(ResourceBookingRestriction::isValidTimeSlot($timeSlot, $timeSlotType, $resource, strtotime('+7 days', $start), strtotime('+7 days', $end)));
    }

    public function testRepetitionOverADaylightSavingTimeChangeIsValid(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Zurich');

        try {
            [$timeSlot, $timeSlotType, $resource] = $this->timeSlotFixture();

            // The clocks change on 2030-03-31; "+7 days" keeps the wall clock time
            $start = strtotime('+7 days', strtotime('2030-03-24 08:00'));
            $end = strtotime('+7 days', strtotime('2030-03-24 08:45'));

            $this->assertTrue(ResourceBookingRestriction::isValidTimeSlot($timeSlot, $timeSlotType, $resource, $start, $end));
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function testTimeSlotEndingAtMidnightIsValid(): void
    {
        [$timeSlot, $timeSlotType, $resource] = $this->timeSlotFixture(['startTime' => 23 * 3600, 'endTime' => 24 * 3600]);

        $midnight = strtotime('2030-10-07');

        $this->assertTrue(ResourceBookingRestriction::isValidTimeSlot($timeSlot, $timeSlotType, $resource, $midnight + 23 * 3600, $midnight + 24 * 3600));
    }

    public static function invalidTimeSlotProvider(): iterable
    {
        yield 'time slot not published' => [['published' => ''], [], [], '08:00', '08:45'];
        yield 'timetable not published' => [[], ['published' => ''], [], '08:00', '08:45'];
        yield 'time slot of another timetable' => [['pid' => 99], [], [], '08:00', '08:45'];
        yield 'timetable of another resource' => [[], [], ['timeSlotType' => 99], '08:00', '08:45'];
        yield 'wrong start time' => [[], [], [], '07:00', '08:45'];
        yield 'wrong end time' => [[], [], [], '08:00', '23:00'];
        yield 'arbitrary time span' => [[], [], [], '03:00', '23:00'];
        yield 'end before start' => [[], [], [], '08:45', '08:00'];
    }

    /**
     * @param array<string, mixed> $timeSlot
     * @param array<string, mixed> $timeSlotType
     * @param array<string, mixed> $resource
     */
    #[DataProvider('invalidTimeSlotProvider')]
    public function testInvalidTimeSlot(array $timeSlot, array $timeSlotType, array $resource, string $start, string $end): void
    {
        [$validTimeSlot, $validTimeSlotType, $validResource] = $this->timeSlotFixture();

        $this->assertFalse(ResourceBookingRestriction::isValidTimeSlot(
            array_merge($validTimeSlot, $timeSlot),
            array_merge($validTimeSlotType, $timeSlotType),
            array_merge($validResource, $resource),
            strtotime('2030-10-07 '.$start),
            strtotime('2030-10-07 '.$end),
        ));
    }

    public function testMissingTimeSlotIsInvalid(): void
    {
        [, $timeSlotType, $resource] = $this->timeSlotFixture();

        $this->assertFalse(ResourceBookingRestriction::isValidTimeSlot(null, $timeSlotType, $resource, strtotime('2030-10-07 08:00'), strtotime('2030-10-07 08:45')));
        $this->assertFalse(ResourceBookingRestriction::isValidTimeSlot($this->timeSlotFixture()[0], null, $resource, strtotime('2030-10-07 08:00'), strtotime('2030-10-07 08:45')));
    }

    /**
     * @param array<string, mixed> $timeSlotOverride
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function timeSlotFixture(array $timeSlotOverride = []): array
    {
        return [
            array_merge(['id' => 12, 'pid' => 4, 'published' => '1', 'startTime' => 8 * 3600, 'endTime' => 8 * 3600 + 45 * 60], $timeSlotOverride),
            ['id' => 4, 'published' => '1'],
            ['id' => 3, 'pid' => 7, 'timeSlotType' => 4, 'itemsAvailable' => 1],
        ];
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function blockedPeriod(array $override = []): array
    {
        return array_merge([
            'id' => 1,
            'title' => 'Herbstferien',
            'published' => '1',
            'dateFrom' => strtotime('2030-10-07'),
            'dateTo' => strtotime('2030-10-18'),
            'blockedTimes' => null,
            'scope' => 'all',
            'resourceTypes' => null,
            'resources' => null,
        ], $override);
    }
}
