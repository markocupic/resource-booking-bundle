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

namespace Markocupic\ResourceBookingBundle\Tests\Booking;

use Contao\Config;
use Contao\Controller;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Api\Input\BookingInput;
use Markocupic\ResourceBookingBundle\Api\Input\SelectedSlot;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppConfigResolver;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Booking\BookingRequestHandler;
use Markocupic\ResourceBookingBundle\Booking\BookingSource;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;
use Markocupic\ResourceBookingBundle\Slot\Slot;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingRequestHandlerTest extends ContaoTestCase
{
    private const WEEK = 1795046400;

    private const DAY = 86400;

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA']['tl_resource_booking']);

        parent::tearDown();
    }

    public function testTheResourceMustBelongToTheModule(): void
    {
        $resource = $this->mockResource(pid: 2);

        $this->assertSame($resource, $this->getHandler(resource: $resource)->getResource($this->input(), $this->appState([2, 4])));

        $this->expectException(\Exception::class);

        $this->getHandler(resource: $resource)->getResource($this->input(), $this->appState([4]));
    }

    public function testAnUnpublishedResourceIsRejected(): void
    {
        $this->expectException(\Exception::class);

        $this->getHandler(resource: false)->getResource($this->input(), $this->appState([2]));
    }

    /**
     * Two selected slots, repeated for two more weeks: 6 slots, sorted by start time.
     */
    public function testCreatesTheSelectedSlotsAndTheirRepetitions(): void
    {
        $input = $this->input(
            slots: [new SelectedSlot(5, self::WEEK + 3 * self::DAY, self::WEEK + 3 * self::DAY + 3600), new SelectedSlot(4, self::WEEK, self::WEEK + 3600)],
            repeatUntil: self::WEEK + 14 * self::DAY,
        );

        $slots = $this->getHandler()->createSlots($input, $this->mockResource(), $this->appState([2]), 'uuid-1');

        $this->assertCount(6, $slots);
        $this->assertSame(
            [self::WEEK, self::WEEK + 3 * self::DAY, self::WEEK + 7 * self::DAY, self::WEEK + 10 * self::DAY, self::WEEK + 14 * self::DAY, self::WEEK + 17 * self::DAY],
            array_map(static fn (Slot $slot): int => $slot->startTime, $slots->all()),
        );
    }

    /**
     * A manipulated request cannot repeat beyond the last week the resource can be booked.
     */
    public function testRepetitionsAreLimitedToTheLastBookableWeek(): void
    {
        $input = $this->input(repeatUntil: self::WEEK + 520 * 7 * self::DAY);

        $slots = $this->getHandler(lastPermittedWeek: self::WEEK + 7 * self::DAY)->createSlots($input, $this->mockResource(), $this->appState([2]), 'uuid-1');

        $this->assertCount(2, $slots);
    }

    public function testTheSlotsContainTheBookingData(): void
    {
        $input = $this->input(formFields: ['phone' => '079', 'member' => '99', 'itemsBooked' => '7']);

        $slots = $this->getHandler(postValues: ['bookingDescription' => 'Werken 3b', 'phone' => '079 123', 'member' => '99', 'itemsBooked' => '7'])
            ->createSlots($input, $this->mockResource(), $this->appState([2]), 'uuid-1')
        ;

        $data = $slots->all()[0]->bookingData;

        $this->assertSame(7, $data['member']);
        $this->assertSame(2, $data['itemsBooked']);
        $this->assertSame(33, $data['moduleId']);
        $this->assertSame(BookingSource::WEEK_CALENDAR, $data['bookingSource']);
        $this->assertSame('uuid-1', $data['bookingUuid']);
        $this->assertSame('Werken 3b', $data['description']);
        $this->assertSame(4, $data['timeSlotId']);
        $this->assertSame(self::WEEK, $data['startTime']);

        // Custom fields are added, but cannot replace fields of the bundle
        $this->assertSame('079 123', $data['phone']);
    }

    public function testErrorKeys(): void
    {
        $handler = $this->getHandler();

        $this->assertSame('RBB.ERR.selectBookingDatesPlease', $handler->getErrorKey(new SlotCollection()));
        $this->assertNull($handler->getErrorKey(new SlotCollection($this->slot())));
        $this->assertSame('RBB.ERR.invalidTimeSlot', $handler->getErrorKey(new SlotCollection($this->slot(isValidTimeSlot: false))));
        $this->assertSame('RBB.ERR.invalidItemsBooked', $handler->getErrorKey(new SlotCollection($this->slot(requestedItems: 0))));
        $this->assertSame('RBB.ERR.slotInBlockedPeriod', $handler->getErrorKey(new SlotCollection($this->slot(blockedPeriodId: 3))));
        $this->assertSame('RBB.ERR.resourceIsAlreadyFullyBooked', $handler->getErrorKey(new SlotCollection($this->slot(bookings: [['member' => 9, 'itemsBooked' => 2]]))));
        $this->assertSame('RBB.ERR.notEnoughItemsAvailable', $handler->getErrorKey(new SlotCollection($this->slot(requestedItems: 2, bookings: [['member' => 9, 'itemsBooked' => 1]]))));
    }

    /**
     * @param list<SelectedSlot>|null $slots
     * @param array<string, mixed>    $formFields
     */
    private function input(array|null $slots = null, int $repeatUntil = self::WEEK, array $formFields = []): BookingInput
    {
        return new BookingInput(3, $slots ?? [new SelectedSlot(4, self::WEEK, self::WEEK + 3600)], 2, $repeatUntil, 'Werken 3b', $formFields);
    }

    /**
     * @param list<int> $resourceTypes
     */
    private function appState(array $resourceTypes): AppState
    {
        return new AppState(
            '33_0',
            $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33, 'resourceBooking_resourceTypes' => implode(',', $resourceTypes)]),
            $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]),
            null,
            null,
            new AppConfig(),
            'monday',
            self::WEEK,
            self::WEEK,
            self::WEEK,
            'de',
        );
    }

    private function mockResource(int $pid = 2): ResourceBookingResourceModel
    {
        return $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 3, 'pid' => $pid, 'title' => 'Werkraum', 'itemsAvailable' => 2]);
    }

    /**
     * @param list<array<string, mixed>> $bookings
     */
    private function slot(bool $isValidTimeSlot = true, int $requestedItems = 1, int|null $blockedPeriodId = null, array $bookings = []): Slot
    {
        $startTime = time() + 7 * self::DAY;

        return new Slot(
            timeSlotId: 4,
            resource: new ResourceRules(id: 3, itemsAvailable: 2),
            startTime: $startTime,
            endTime: $startTime + 3600,
            now: time(),
            loggedInMemberId: 7,
            requestedItems: $requestedItems,
            isValidTimeSlot: $isValidTimeSlot,
            blockedPeriodId: $blockedPeriodId,
            repeatUntilWeekStart: $startTime,
            bookings: $bookings,
        );
    }

    /**
     * @param ResourceBookingResourceModel|false|null $resource   false: not found
     * @param array<string, string>                   $postValues values of Input::post()
     */
    private function getHandler(ResourceBookingResourceModel|false|null $resource = null, int $lastPermittedWeek = self::WEEK + 520 * 7 * self::DAY, array $postValues = []): BookingRequestHandler
    {
        $resourceAdapter = $this->createAdapterStub(['findPublishedByPk']);
        $resourceAdapter
            ->method('findPublishedByPk')
            ->willReturn(false === $resource ? null : ($resource ?? $this->mockResource()))
        ;

        $dateAdapter = $this->createAdapterStub(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $time): string => date('d.m.Y H:i', $time))
        ;

        $configAdapter = $this->createAdapterStub(['get']);
        $configAdapter
            ->method('get')
            ->willReturn('d.m.Y H:i')
        ;

        $inputAdapter = $this->createAdapterStub(['post']);
        $inputAdapter
            ->method('post')
            ->willReturnCallback(static fn (string $key): string|null => $postValues[$key] ?? null)
        ;

        $controllerAdapter = $this->createAdapterStub(['loadDataContainer']);
        $controllerAdapter
            ->method('loadDataContainer')
            ->willReturnCallback(
                static function (): void {
                    $GLOBALS['TL_DCA']['tl_resource_booking'] = ['fields' => []];
                },
            )
        ;

        $framework = $this->createContaoFrameworkStub([
            ResourceBookingResourceModel::class => $resourceAdapter,
            Date::class => $dateAdapter,
            Config::class => $configAdapter,
            Input::class => $inputAdapter,
            Controller::class => $controllerAdapter,
        ]);

        $loggedInUser = $this->createStub(LoggedInFrontendUser::class);
        $loggedInUser
            ->method('getLoggedInUser')
            ->willReturn($this->createClassWithPropertiesStub(FrontendUser::class, ['id' => 7, 'firstname' => 'Hans', 'lastname' => 'Muster']))
        ;

        // The factory creates slots without checks: only the times are relevant here
        $slotFactory = $this->createStub(SlotFactory::class);
        $slotFactory
            ->method('create')
            ->willReturnCallback(
                static fn (int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime): Slot => new Slot(
                    timeSlotId: $timeSlotId,
                    resource: new ResourceRules(id: 3, itemsAvailable: 2),
                    startTime: $startTime,
                    endTime: $endTime,
                    now: 0,
                    weekStart: $startTime - (($startTime - self::WEEK) % (7 * self::DAY)),
                ),
            )
        ;

        $weekBoundaries = $this->createStub(WeekBoundaries::class);
        $weekBoundaries
            ->method('getLastPermittedWeek')
            ->willReturn($lastPermittedWeek)
        ;

        $appConfigResolver = $this->createStub(AppConfigResolver::class);
        $appConfigResolver
            ->method('getBeginWeek')
            ->willReturn('monday')
        ;

        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnArgument(0)
        ;

        return new BookingRequestHandler($framework, $loggedInUser, $slotFactory, $weekBoundaries, $appConfigResolver, $translator, new MockClock());
    }
}
