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

namespace Markocupic\ResourceBookingBundle\Tests\Restriction;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;
use Markocupic\ResourceBookingBundle\Restriction\TimeSlotRules;
use PHPUnit\Framework\Attributes\DataProvider;

class TimeSlotRulesTest extends ContaoTestCase
{
    public function testBeginWeekIsReadFromTheTimetable(): void
    {
        $this->assertSame('sunday', TimeSlotRules::getBeginWeek(['beginWeek' => 'sunday']));
        $this->assertSame('saturday', TimeSlotRules::getBeginWeek(['beginWeek' => ' Saturday ']));
        $this->assertSame('sunday', TimeSlotRules::getBeginWeek($this->createClassWithPropertiesStub(ResourceBookingTimeSlotTypeModel::class, ['beginWeek' => 'sunday'])));
    }

    public function testBeginWeekFallsBackToMonday(): void
    {
        $this->assertSame('monday', TimeSlotRules::getBeginWeek(null));
        $this->assertSame('monday', TimeSlotRules::getBeginWeek([]));
        $this->assertSame('monday', TimeSlotRules::getBeginWeek(['beginWeek' => null]));
        $this->assertSame('monday', TimeSlotRules::getBeginWeek(['beginWeek' => 'someday']));
    }

    public function testTimeSlotWithoutWeekdaysIsAvailableOnEveryWeekday(): void
    {
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday(null, 'sunday'));
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday([], 'sunday'));
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday(['weekdays' => null], 'sunday'));
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday(['weekdays' => ''], 'sunday'));
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday(['weekdays' => ''], 'sunday'));
    }

    public function testTimeSlotIsOnlyAvailableOnItsWeekdays(): void
    {
        $row = ['weekdays' => 'monday,tuesday,thursday'];

        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday($row, 'monday'));
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday($row, 'Thursday'));
        $this->assertFalse(TimeSlotRules::isAvailableOnWeekday($row, 'wednesday'));

        $model = $this->createClassWithPropertiesStub(ResourceBookingTimeSlotModel::class, ['weekdays' => 'friday']);
        $this->assertTrue(TimeSlotRules::isAvailableOnWeekday($model, 'friday'));
        $this->assertFalse(TimeSlotRules::isAvailableOnWeekday($model, 'monday'));
    }

    public function testValidTimeSlot(): void
    {
        [$timeSlot, $timeSlotType, $resource] = $this->timeSlotFixture();

        // 08:00 - 08:45 on the day of the slot
        $start = strtotime('2030-10-07 08:00');
        $end = strtotime('2030-10-07 08:45');

        $this->assertTrue(TimeSlotRules::isValidTimeSlot($timeSlot, $timeSlotType, ResourceRules::fromRow($resource), $start, $end));

        // Repetition one week later
        $this->assertTrue(TimeSlotRules::isValidTimeSlot($timeSlot, $timeSlotType, ResourceRules::fromRow($resource), strtotime('+7 days', $start), strtotime('+7 days', $end)));
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

            $this->assertTrue(TimeSlotRules::isValidTimeSlot($timeSlot, $timeSlotType, ResourceRules::fromRow($resource), $start, $end));
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function testTimeSlotEndingAtMidnightIsValid(): void
    {
        [$timeSlot, $timeSlotType, $resource] = $this->timeSlotFixture(['startTime' => 23 * 3600, 'endTime' => 24 * 3600]);

        $midnight = strtotime('2030-10-07');

        $this->assertTrue(TimeSlotRules::isValidTimeSlot($timeSlot, $timeSlotType, ResourceRules::fromRow($resource), $midnight + 23 * 3600, $midnight + 24 * 3600));
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

        $this->assertFalse(TimeSlotRules::isValidTimeSlot(
            array_merge($validTimeSlot, $timeSlot),
            array_merge($validTimeSlotType, $timeSlotType),
            ResourceRules::fromRow(array_merge($validResource, $resource)),
            strtotime('2030-10-07 '.$start),
            strtotime('2030-10-07 '.$end),
        ));
    }

    public function testMissingTimeSlotIsInvalid(): void
    {
        [, $timeSlotType, $resource] = $this->timeSlotFixture();

        $this->assertFalse(TimeSlotRules::isValidTimeSlot(null, $timeSlotType, ResourceRules::fromRow($resource), strtotime('2030-10-07 08:00'), strtotime('2030-10-07 08:45')));
        $this->assertFalse(TimeSlotRules::isValidTimeSlot($this->timeSlotFixture()[0], null, ResourceRules::fromRow($resource), strtotime('2030-10-07 08:00'), strtotime('2030-10-07 08:45')));
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
}
