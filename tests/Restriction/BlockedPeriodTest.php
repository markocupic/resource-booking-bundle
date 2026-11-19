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
use Markocupic\ResourceBookingBundle\Restriction\BlockedPeriod;
use Markocupic\ResourceBookingBundle\Restriction\BlockedTime;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;

class BlockedPeriodTest extends ContaoTestCase
{
    public function testBlockedPeriodCoversTheDaysBetweenDateFromAndDateTo(): void
    {
        $period = $this->blockedPeriod();
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
        // Last day is inclusive
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-18 16:00'), strtotime('2030-10-18 17:00')));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-19 08:00'), strtotime('2030-10-19 09:00')));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-06 08:00'), strtotime('2030-10-06 09:00')));
    }

    /**
     * Mode "slots": only the blocked time slots of the selected resource are covered,
     * on their day, whatever the times of the time slot are.
     */
    public function testBlockedSlotsOnlyBlockTheirTimeSlotsOfTheirResource(): void
    {
        $period = $this->blockedPeriod([
            'mode' => 'slots',
            'resource' => 3,
            'scope' => 'all',
            'blockedSlots' => serialize([['timeSlot' => 7, 'date' => '2030-10-08']]),
        ]);

        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 08:00'), strtotime('2030-10-08 09:00'), 7));
        // The times of the time slot have been changed
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 10:00'), strtotime('2030-10-08 12:00'), 7));
        // Other time slot, other day within dateFrom and dateTo
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 08:00'), strtotime('2030-10-08 09:00'), 8));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-09 08:00'), strtotime('2030-10-09 09:00'), 7));
        // Without time slot ID
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 08:00'), strtotime('2030-10-08 09:00')));
        // Other resource, even with the scope "all"
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow(['id' => 5, 'pid' => 4]), strtotime('2030-10-08 08:00'), strtotime('2030-10-08 09:00'), 7));
        // Inactive
        $this->assertFalse(BlockedPeriod::fromRow(['published' => ''] + $period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 08:00'), strtotime('2030-10-08 09:00'), 7));
    }

    public function testInactiveBlockedPeriodBlocksNothing(): void
    {
        $period = $this->blockedPeriod(['published' => '']);

        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow(['id' => 3, 'pid' => 4]), strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
    }

    public function testBlockedPeriodWithoutEndDateIsUnlimited(): void
    {
        $period = $this->blockedPeriod(['dateTo' => 0]);
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2045-06-01 08:00'), strtotime('2045-06-01 09:00')));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-06 08:00'), strtotime('2030-10-06 09:00')));
    }

    public function testBlockedTimesOnlyBlockTheirWeekdays(): void
    {
        // 2030-10-07 is a monday, 2030-10-12 a saturday
        $period = $this->blockedPeriod(['blockedTimes' => serialize([
            ['weekday' => 'saturday', 'timeFrom' => '', 'timeTo' => ''],
            ['weekday' => 'sunday', 'timeFrom' => '', 'timeTo' => ''],
        ])]);
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-12 08:00'), strtotime('2030-10-12 09:00')));
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-13 23:00'), strtotime('2030-10-14 00:00')));
    }

    public function testBlockedTimesWithTimesOnlyBlockOverlappingSlots(): void
    {
        $period = $this->blockedPeriod(['blockedTimes' => serialize([
            ['weekday' => 'monday', 'timeFrom' => '12:00', 'timeTo' => '18:00'],
            ['weekday' => 'friday', 'timeFrom' => '14:00', 'timeTo' => ''],
        ])]);
        $resource = ['id' => 3, 'pid' => 4];

        // Monday
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-07 08:00'), strtotime('2030-10-07 09:00')));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-07 11:00'), strtotime('2030-10-07 12:00')));
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-07 11:30'), strtotime('2030-10-07 12:15')));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-07 18:00'), strtotime('2030-10-07 19:00')));
        // Tuesday: no blocked time
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 13:00'), strtotime('2030-10-08 14:00')));
        // Friday from 14:00 until the end of the day
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-11 13:00'), strtotime('2030-10-11 14:00')));
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-11 20:00'), strtotime('2030-10-11 21:00')));
    }

    public function testDailyBlockedTimeAppliesToEveryDay(): void
    {
        $period = $this->blockedPeriod(['blockedTimes' => serialize([['weekday' => 'daily', 'timeFrom' => '12:00', 'timeTo' => '13:00']])]);
        $resource = ['id' => 3, 'pid' => 4];

        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-08 12:15'), strtotime('2030-10-08 12:45')));
        $this->assertTrue(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-12 12:15'), strtotime('2030-10-12 12:45')));
        $this->assertFalse(BlockedPeriod::fromRow($period)->covers(ResourceRules::fromRow($resource), strtotime('2030-10-12 13:00'), strtotime('2030-10-12 14:00')));
    }

    public function testInvalidBlockedTimeRowsAreIgnored(): void
    {
        $period = $this->blockedPeriod(['blockedTimes' => serialize([['weekday' => 'someday'], 'foo'])]);

        $this->assertSame([], BlockedTime::listFromSerialized($period['blockedTimes']));
    }

    public function testBlockedPeriodScope(): void
    {
        $start = strtotime('2030-10-07 08:00');
        $end = strtotime('2030-10-07 09:00');

        $byType = $this->blockedPeriod(['scope' => 'resourceTypes', 'resourceTypes' => '4']);
        $this->assertTrue(BlockedPeriod::fromRow($byType)->covers(ResourceRules::fromRow(['id' => 3, 'pid' => 4]), $start, $end));
        $this->assertFalse(BlockedPeriod::fromRow($byType)->covers(ResourceRules::fromRow(['id' => 3, 'pid' => 5]), $start, $end));

        $byResource = $this->blockedPeriod(['scope' => 'resources', 'resources' => '3,8']);
        $this->assertTrue(BlockedPeriod::fromRow($byResource)->covers(ResourceRules::fromRow(['id' => 8, 'pid' => 4]), $start, $end));
        $this->assertFalse(BlockedPeriod::fromRow($byResource)->covers(ResourceRules::fromRow(['id' => 9, 'pid' => 4]), $start, $end));
    }

    public function testEmptyBlockedTimesMeanTheStartAndTheEndOfTheDay(): void
    {
        $period = BlockedPeriod::fromRow($this->blockedPeriod(['blockedTimes' => serialize([
            ['weekday' => 'Saturday', 'timeFrom' => '', 'timeTo' => ''],
            ['weekday' => 'daily', 'timeFrom' => '12:00', 'timeTo' => ''],
        ])]));

        $this->assertEquals([new BlockedTime('saturday', 0, 86400), new BlockedTime('daily', 43200, 86400)], $period->blockedTimes);
        $this->assertTrue($period->blockedTimes[0]->isWholeDay());
        $this->assertFalse($period->blockedTimes[1]->isWholeDay());
    }

    public function testLabelFallsBackToTheDescriptionAndTheId(): void
    {
        $this->assertSame('Herbstferien', BlockedPeriod::fromRow($this->blockedPeriod())->getLabel());
        $this->assertSame('Geschlossen', BlockedPeriod::fromRow($this->blockedPeriod(['title' => ' ', 'description' => 'Geschlossen']))->getLabel());
        $this->assertSame('#1', BlockedPeriod::fromRow($this->blockedPeriod(['title' => '']))->getLabel());
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function blockedPeriod(array $override = []): array
    {
        return array_merge(
            [
                'id' => 1,
                'title' => 'Herbstferien',
                'published' => '1',
                'dateFrom' => strtotime('2030-10-07'),
                'dateTo' => strtotime('2030-10-18'),
                'blockedTimes' => null,
                'scope' => 'all',
                'resourceTypes' => null,
                'resources' => null,
            ],
            $override,
        );
    }
}
