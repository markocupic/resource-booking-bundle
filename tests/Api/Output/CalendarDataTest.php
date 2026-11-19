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

namespace Markocupic\ResourceBookingBundle\Tests\Api\Output;

use Markocupic\ResourceBookingBundle\Api\Output\CalendarData;
use PHPUnit\Framework\TestCase;

class CalendarDataTest extends TestCase
{
    public function testSendsTheExpectedKeys(): void
    {
        $this->assertSame(
            ['filterBoard', 'hasLoggedInUser', 'loggedInUser', 'activeWeekStart', 'activeWeek', 'bookingRepeatsSelection', 'weekdays', 'activeResourceType', 'activeResourceTypeId', 'activeResource', 'activeResourceId', 'rows', 'timeSlots', 'isReady'],
            array_keys($this->calendarData()->toArray()),
        );

        $this->assertSame(
            ['resourceTypes', 'resources', 'jumpPrevWeek', 'jumpNextWeek', 'weekSelection', 'firstPermittedWeekStart', 'lastPermittedWeekStart'],
            array_keys($this->calendarData()->toArray()['filterBoard']),
        );
    }

    /**
     * Only the name of the logged-in member, never e-mail, address or security fields.
     */
    public function testSendsOnlyTheNameOfTheMember(): void
    {
        $data = $this->calendarData(member: ['id' => 7, 'firstname' => 'Hans', 'lastname' => 'Muster', 'email' => 'h@example.com', 'password' => 'x', 'secret' => 'totp', 'backupCodes' => 'codes'])->toArray();

        $this->assertTrue($data['hasLoggedInUser']);
        $this->assertSame(['firstname' => 'Hans', 'lastname' => 'Muster'], $data['loggedInUser']);
    }

    public function testGuests(): void
    {
        $data = $this->calendarData()->toArray();

        $this->assertFalse($data['hasLoggedInUser']);
        $this->assertNull($data['loggedInUser']);
    }

    /**
     * Resource and resource types: only the fields of the front end.
     */
    public function testSendsOnlyThePermittedFieldsOfResourcesAndResourceTypes(): void
    {
        $resource = ['id' => '3', 'pid' => 2, 'title' => 'Werkraum', 'description' => '<p>Text</p>', 'itemsAvailable' => '4', 'notification' => 5, 'timeSlotType' => 1];
        $resourceType = ['id' => '2', 'title' => 'Räume', 'tstamp' => 123];

        $data = $this->calendarData(resourceTypes: [$resourceType], resources: [$resource], activeResourceType: $resourceType, activeResource: $resource)->toArray();

        $this->assertSame([['id' => 2, 'title' => 'Räume']], $data['filterBoard']['resourceTypes']);
        $this->assertSame([['id' => 3, 'title' => 'Werkraum', 'description' => '<p>Text</p>', 'itemsAvailable' => 4]], $data['filterBoard']['resources']);
        $this->assertSame(['id' => 2, 'title' => 'Räume'], $data['activeResourceType']);
        $this->assertSame(['id' => 3, 'title' => 'Werkraum', 'description' => '<p>Text</p>', 'itemsAvailable' => 4], $data['activeResource']);
        $this->assertSame(2, $data['activeResourceTypeId']);
        $this->assertSame(3, $data['activeResourceId']);
    }

    /**
     * Nothing selected: empty objects and "undefined" (option value of the dropdowns).
     */
    public function testNoSelection(): void
    {
        $data = $this->calendarData()->toArray();

        $this->assertSame([], $data['activeResourceType']);
        $this->assertSame([], $data['activeResource']);
        $this->assertSame('undefined', $data['activeResourceTypeId']);
        $this->assertSame('undefined', $data['activeResourceId']);
    }

    public function testSendsThePermittedWeeks(): void
    {
        $data = $this->calendarData()->toArray();

        $this->assertSame(1000, $data['filterBoard']['firstPermittedWeekStart']);
        $this->assertSame(2000, $data['filterBoard']['lastPermittedWeekStart']);
    }

    /**
     * @param list<array<string, mixed>> $resourceTypes
     * @param list<array<string, mixed>> $resources
     * @param array<string, mixed>|null  $member
     * @param array<string, mixed>|null  $activeResourceType
     * @param array<string, mixed>|null  $activeResource
     */
    private function calendarData(array $resourceTypes = [], array $resources = [], array|null $member = null, array|null $activeResourceType = null, array|null $activeResource = null): CalendarData
    {
        return new CalendarData(
            resourceTypes: $resourceTypes,
            resources: $resources,
            jumpPrevWeek: ['disabled' => true, 'tstamp' => 1000],
            jumpNextWeek: ['disabled' => false, 'tstamp' => 1500],
            weekSelection: [],
            firstPermittedWeekStart: 1000,
            lastPermittedWeekStart: 2000,
            member: $member,
            activeWeekStart: 1000,
            activeWeek: [],
            bookingRepeatsSelection: [],
            weekdays: [],
            activeResourceType: $activeResourceType,
            activeResource: $activeResource,
            rows: [],
            timeSlots: [],
        );
    }
}
