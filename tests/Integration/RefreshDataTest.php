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

namespace Markocupic\ResourceBookingBundle\Tests\Integration;

use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Data of the week calendar (GET /_rbb/api/calendar): selected week and resource, slots,
 * bookings and the data of other members that may be displayed.
 */
class RefreshDataTest extends IntegrationTestCase
{
    private int $page;

    private int $resourceType;

    private int $resource;

    private int $timeSlot;

    /**
     * First day of the next week (monday).
     */
    private int $weekStart;

    protected function setUp(): void
    {
        parent::setUp();

        [$timetable, [$this->timeSlot]] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        [$this->resourceType, $this->resource] = $this->createResource($timetable, 1, ['weeksAhead' => 4]);
        $this->page = $this->createPage();
        $this->weekStart = strtotime('monday next week');
    }

    public function testReturnsTheSelectedWeekAndResource(): void
    {
        $module = $this->createModule([$this->resourceType]);

        $response = $this->refresh($module);

        $this->assertSame('success', $response['status'], json_encode($response));
        $this->assertSame($this->resourceType, $response['data']['activeResourceTypeId']);
        $this->assertSame($this->resource, $response['data']['activeResourceId']);
        $this->assertSame($this->weekStart, $response['data']['activeWeekStart']);

        // One time slot: one row with a cell per weekday
        $this->assertCount(1, $response['fullData']['rows']);
        $this->assertCount(7, $response['fullData']['rows'][0]['cellData']);

        $cell = $this->findCell($response, $this->weekStart + 8 * 3600);

        $this->assertSame($this->timeSlot, $cell['timeSlotId']);
        $this->assertSame($this->resource, $cell['resourceId']);
        $this->assertFalse($cell['hasBookings']);
        $this->assertSame(1, $cell['remainingItems']);
    }

    public function testAGuestSeesTheSlotsButCannotBook(): void
    {
        $module = $this->createModule([$this->resourceType]);

        $response = $this->refresh($module);

        $this->assertFalse($response['fullData']['hasLoggedInUser']);
        $this->assertNull($response['fullData']['loggedInUser']);
        $this->assertFalse($this->findCell($response, $this->weekStart + 8 * 3600)['isMemberLoggedIn']);
    }

    public function testTheLoggedInMemberGetsOnlyTheirName(): void
    {
        $module = $this->createModule([$this->resourceType]);
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->refresh($module);

        $this->assertTrue($response['fullData']['hasLoggedInUser']);
        $this->assertSame(['firstname' => 'Alice', 'lastname' => 'Test'], $response['fullData']['loggedInUser']);
        $this->assertTrue($this->findCell($response, $this->weekStart + 8 * 3600)['isBookable']);
    }

    public function testTheWeekViewIsRenderedOnTheServer(): void
    {
        $module = $this->createModule([$this->resourceType]);
        $this->createMember('alice');
        $this->logIn('alice');

        $html = $this->refresh($module)['data']['weekCalendarHtml'];

        // Header, filter with the selection, table with a "book" button per free slot
        $this->assertStringContainsString('<h2 class="rbb-header-headline">Werkraum</h2>', $html);
        $this->assertStringContainsString(\sprintf('<option value="%d" selected>', $this->resource), $html);
        $this->assertStringContainsString(\sprintf('value="%d" data-rbb-date="%s" selected>', $this->weekStart, date('Y-m-d', $this->weekStart)), $html);
        $this->assertStringContainsString('data-rbb-action="book"', $html);
        $this->assertStringContainsString(\sprintf('data-rbb-week="%d"', strtotime('+1 week', $this->weekStart)), $html);

        // No client side template syntax
        $this->assertStringNotContainsString('v-if', $html);
        $this->assertStringNotContainsString('[[', $html);
    }

    public function testGuestsGetNoBookingButtonsInTheWeekView(): void
    {
        $module = $this->createModule([$this->resourceType]);

        $html = $this->refresh($module)['data']['weekCalendarHtml'];

        $this->assertStringContainsString('rbb-booking-table-main', $html);
        $this->assertStringNotContainsString('data-rbb-action="book"', $html);
    }

    public function testABookedOutSlotIsNotBookable(): void
    {
        $module = $this->createModule([$this->resourceType]);
        $bob = $this->createMember('bob');
        $this->insertBookingRow($this->resource, $bob, $this->timeSlot, $this->weekStart + 8 * 3600, $this->weekStart + 9 * 3600);
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->refresh($module);
        $cell = $this->findCell($response, $this->weekStart + 8 * 3600);

        $this->assertTrue($cell['hasBookings']);
        $this->assertTrue($cell['isFullyBooked']);
        $this->assertFalse($cell['isBookable']);
        $this->assertSame(0, $cell['remainingItems']);
        $this->assertNull($cell['memberBooking']);

        // The same time slot on tuesday is free
        $this->assertTrue($this->findCell($response, strtotime('+1 day', $this->weekStart) + 8 * 3600)['isBookable']);
    }

    public function testBookingsOfOtherMembersAreAnonymousIfNoMemberFieldIsDisplayed(): void
    {
        $module = $this->createModule([$this->resourceType], [
            'resourceBooking_displayClientPersonalData' => 0,
            'resourceBooking_clientPersonalData' => 'firstname,lastname',
        ]);

        $bob = $this->createMember('bob');
        $this->insertBookingRow($this->resource, $bob, $this->timeSlot, $this->weekStart + 8 * 3600, $this->weekStart + 9 * 3600);
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->refresh($module);
        $booking = $this->findCell($response, $this->weekStart + 8 * 3600)['bookings'][0];

        $this->assertTrue($booking['bookedByAnonymous']);
        $this->assertFalse($booking['isOwn']);
        $this->assertFalse($booking['canCancel']);
        $this->assertArrayNotHasKey('bookedByFirstname', $booking);
        $this->assertArrayNotHasKey('member', $booking);

        // Nothing of the other member is sent to the browser
        $json = json_encode($response, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Bob', $json);
        $this->assertStringNotContainsString('bob@example.com', $json);
    }

    public function testTheJsonResponseContainsTheHtmlButNotTheDataOfTheCalendar(): void
    {
        $module = $this->createModule([$this->resourceType]);
        $alice = $this->createMember('alice');
        $this->insertBookingRow($this->resource, $alice, $this->timeSlot, $this->weekStart + 8 * 3600, $this->weekStart + 9 * 3600);
        $this->logIn('alice');

        $response = $this->refresh($module);

        $this->assertEqualsCanonicalizing(['weekCalendarHtml', 'activeResourceTypeId', 'activeResourceId', 'activeWeekStart'], array_keys($response['data']));
        $this->assertStringContainsString('Alice Test', $response['data']['weekCalendarHtml']);
        $this->assertArrayHasKey('rows', $response['fullData']);
    }

    public function testTheSelectedMemberFieldsAreDisplayedInTheWeekView(): void
    {
        $module = $this->createModule([$this->resourceType], [
            'resourceBooking_displayClientPersonalData' => 1,
            'resourceBooking_clientPersonalData' => 'firstname,lastname,company,city',
        ]);

        $bob = $this->createMember('bob');
        $this->connection->update('tl_member', ['company' => 'Bob AG', 'city' => 'Bobwil'], ['id' => $bob]);
        $this->insertBookingRow($this->resource, $bob, $this->timeSlot, $this->weekStart + 8 * 3600, $this->weekStart + 9 * 3600);
        $this->createMember('alice');
        $this->logIn('alice');

        $html = $this->refresh($module)['data']['weekCalendarHtml'];

        $this->assertStringContainsString('data-field="company"', $html);
        $this->assertStringContainsString('Bob AG', $html);
        $this->assertStringContainsString('Bobwil', $html);
    }

    public function testOnlyThePermittedMemberFieldsOfOtherMembersAreDisplayed(): void
    {
        // email is not permitted in the bundle configuration (displayable_member_fields)
        $module = $this->createModule([$this->resourceType], [
            'resourceBooking_displayClientPersonalData' => 1,
            'resourceBooking_clientPersonalData' => 'firstname,lastname,email',
        ]);

        $bob = $this->createMember('bob');
        $this->insertBookingRow($this->resource, $bob, $this->timeSlot, $this->weekStart + 8 * 3600, $this->weekStart + 9 * 3600);
        $this->createMember('alice');
        $this->logIn('alice');

        $response = $this->refresh($module);
        $booking = $this->findCell($response, $this->weekStart + 8 * 3600)['bookings'][0];

        $this->assertFalse($booking['bookedByAnonymous']);
        $this->assertSame('Bob Test', $booking['bookedByFullname']);
        $this->assertSame('Bob', $booking['bookedByFirstname']);
        $this->assertArrayNotHasKey('bookedByEmail', $booking);
        $this->assertStringNotContainsString('bob@example.com', json_encode($response));
    }

    public function testTheOwnBookingIsMarkedAndCanBeCanceled(): void
    {
        $module = $this->createModule([$this->resourceType], ['resourceBooking_displayClientPersonalData' => 0]);
        $alice = $this->createMember('alice');
        $booking = $this->insertBookingRow($this->resource, $alice, $this->timeSlot, $this->weekStart + 8 * 3600, $this->weekStart + 9 * 3600, ['bookingUuid' => 'alice-uuid']);
        $this->logIn('alice');

        $response = $this->refresh($module);
        $cell = $this->findCell($response, $this->weekStart + 8 * 3600);

        $this->assertSame(['id' => $booking, 'bookingUuid' => 'alice-uuid'], $cell['memberBooking']);
        $this->assertTrue($cell['isCancelable']);
        $this->assertTrue($cell['bookings'][0]['isOwn']);
        $this->assertTrue($cell['bookings'][0]['canCancel']);
        // The logged-in member always sees their own name
        $this->assertSame('Alice Test', $cell['bookings'][0]['bookedByFullname']);
    }

    public function testTheWeekIsLimitedToThePermittedWeeks(): void
    {
        $module = $this->createModule([$this->resourceType]);

        // weeksAhead = 4
        $response = $this->refresh($module, strtotime('+20 weeks', $this->weekStart));
        $lastWeek = strtotime('+4 weeks', strtotime('monday this week'));

        $this->assertSame($lastWeek, $response['fullData']['filterBoard']['lastPermittedWeekStart']);
        $this->assertSame($lastWeek, $response['data']['activeWeekStart']);
        $this->assertTrue($response['fullData']['filterBoard']['jumpNextWeek']['disabled']);
    }

    public function testTheFirstResourceIsSelectedOnTheFirstRequest(): void
    {
        $module = $this->createModule([$this->resourceType]);

        $request = Request::create('https://example.com/_rbb/api/calendar', 'GET', ['initial' => '1']);
        $response = $this->sendApiRequest($request, RefreshDataController::ACTION, $module, $this->page);

        $this->assertSame($this->resource, $response['data']['activeResourceId']);
        $this->assertSame(strtotime('monday this week'), $response['data']['activeWeekStart']);
    }

    public function testResourcesOfOtherModulesCannotBeSelected(): void
    {
        [$otherTimetable] = $this->createTimetable([[8 * 3600, 9 * 3600]]);
        [$otherType, $otherResource] = $this->createResource($otherTimetable);
        $module = $this->createModule([$this->resourceType]);

        $request = Request::create(
            'https://example.com/_rbb/api/calendar',
            'GET',
            [
                'resType' => (string) $otherType,
                'res' => (string) $otherResource,
                'date' => date('Y-m-d', $this->weekStart),
            ],
        );

        $response = $this->sendApiRequest($request, RefreshDataController::ACTION, $module, $this->page);

        $this->assertNotSame($otherResource, $response['data']['activeResourceId']);
        $this->assertNotSame($otherType, $response['data']['activeResourceTypeId']);
        $this->assertSame([$this->resourceType], array_map('intval', array_column($response['fullData']['filterBoard']['resourceTypes'], 'id')));
    }

    /**
     * @return array<string, mixed>
     */
    private function refresh(int $module, int|null $date = null): array
    {
        $request = Request::create(
            'https://example.com/_rbb/api/calendar',
            'GET',
            [
                'resType' => (string) $this->resourceType,
                'res' => (string) $this->resource,
                'date' => date('Y-m-d', $date ?? $this->weekStart),
            ],
        );

        return $this->sendApiRequest($request, RefreshDataController::ACTION, $module, $this->page);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function findCell(array $response, int $startTime): array
    {
        foreach ($response['fullData']['rows'] as $row) {
            foreach ($row['cellData'] as $cell) {
                if ($startTime === ($cell['startTime'] ?? null)) {
                    return $cell;
                }
            }
        }

        $this->fail(\sprintf('No cell starts at %s.', date('Y-m-d H:i', $startTime)));
    }
}
