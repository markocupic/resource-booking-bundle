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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

class WeekBoundariesTest extends ContaoTestCase
{
    private const APP_CONFIG = ['beginWeek' => 'monday'];

    private const CURRENT_WEEK = 1000;

    // Resource settings used in most tests: 2 weeks back (-> 500), 4 weeks ahead (-> 2000)
    private const FIRST_WEEK_OF_RESOURCE = 500;

    private const LAST_WEEK_OF_RESOURCE = 2000;

    // Defaults (RbbConfig::RBB_DEFAULT_WEEKS_BACK/AHEAD) if there is no resource
    private const FIRST_WEEK_BY_DEFAULT = 100;

    private const LAST_WEEK_BY_DEFAULT = 6000;

    public function testWithoutResourceTheDefaultsApply(): void
    {
        $weekBoundaries = new WeekBoundaries($this->getFramework(1500));

        $this->assertSame(self::FIRST_WEEK_BY_DEFAULT, $weekBoundaries->getFirstPermittedWeek(self::APP_CONFIG, null));
        $this->assertSame(self::LAST_WEEK_BY_DEFAULT, $weekBoundaries->getLastPermittedWeek(self::APP_CONFIG, null, 1100));
    }

    public function testTheResourceDefinesTheWeeksBackAndAhead(): void
    {
        $weekBoundaries = new WeekBoundaries($this->getFramework(1500));
        $resource = $this->mockResource(false, '');

        $this->assertSame(self::FIRST_WEEK_OF_RESOURCE, $weekBoundaries->getFirstPermittedWeek(self::APP_CONFIG, $resource));
        $this->assertSame(self::LAST_WEEK_OF_RESOURCE, $weekBoundaries->getLastPermittedWeek(self::APP_CONFIG, $resource, 1100));
    }

    public function testBookableUntilBeforeTheWeeksAheadLimitDefinesTheLastWeek(): void
    {
        // "Bookable until" lies in the week starting at 1500.
        $weekBoundaries = new WeekBoundaries($this->getFramework(1500));
        $resource = $this->mockResource(true, '1550');

        $this->assertSame(1500, $weekBoundaries->getLastPermittedWeek(self::APP_CONFIG, $resource, 1100));
    }

    public function testBookableUntilAfterTheWeeksAheadLimitIsIgnored(): void
    {
        $weekBoundaries = new WeekBoundaries($this->getFramework(3000));
        $resource = $this->mockResource(true, '3050');

        $this->assertSame(self::LAST_WEEK_OF_RESOURCE, $weekBoundaries->getLastPermittedWeek(self::APP_CONFIG, $resource, 1100));
    }

    public function testBookableUntilInThePastClampsToTheCurrentWeek(): void
    {
        $weekBoundaries = new WeekBoundaries($this->getFramework(300));
        $resource = $this->mockResource(true, '350');

        $this->assertSame(self::CURRENT_WEEK, $weekBoundaries->getLastPermittedWeek(self::APP_CONFIG, $resource, 1100));
    }

    public function testStorePermittedWeeksWritesTheSession(): void
    {
        $bag = new AttributeBag();
        $weekBoundaries = new WeekBoundaries($this->getFramework(1500));

        $weekBoundaries->storePermittedWeeks($bag, self::APP_CONFIG, $this->mockResource(true, '1550'), 1100);

        $this->assertSame(self::FIRST_WEEK_OF_RESOURCE, $bag->get('tstampFirstPermittedWeek'));
        $this->assertSame('d500', $bag->get('tstampFirstPermittedDate'));
        $this->assertSame(1500, $bag->get('tstampLastPermittedWeek'));
        $this->assertSame('d1500', $bag->get('tstampLastPermittedWeekDate'));
    }

    private function getFramework(int $firstDayOfBookableUntilWeek): ContaoFramework
    {
        $dateHelperAdapter = $this->createAdapterMock(['getFirstDayOfCurrentWeek', 'addWeeksToTime', 'getFirstDayOfWeek']);
        $dateHelperAdapter
            ->method('getFirstDayOfCurrentWeek')
            ->willReturn(self::CURRENT_WEEK)
        ;

        $dateHelperAdapter
            ->method('addWeeksToTime')
            ->willReturnMap([
                [-2, self::CURRENT_WEEK, self::FIRST_WEEK_OF_RESOURCE],
                [4, self::CURRENT_WEEK, self::LAST_WEEK_OF_RESOURCE],
                [-RbbConfig::RBB_DEFAULT_WEEKS_BACK, self::CURRENT_WEEK, self::FIRST_WEEK_BY_DEFAULT],
                [RbbConfig::RBB_DEFAULT_WEEKS_AHEAD, self::CURRENT_WEEK, self::LAST_WEEK_BY_DEFAULT],
            ])
        ;

        $dateHelperAdapter
            ->method('getFirstDayOfWeek')
            ->willReturn($firstDayOfBookableUntilWeek)
        ;

        $dateAdapter = $this->createAdapterMock(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => 'd'.$tstamp)
        ;

        return $this->createContaoFrameworkMock([
            DateHelper::class => $dateHelperAdapter,
            Date::class => $dateAdapter,
        ]);
    }

    private function mockResource(bool $addBookableUntil, string $bookableUntil): ResourceBookingResourceModel
    {
        return $this->createClassWithPropertiesMock(ResourceBookingResourceModel::class, [
            'weeksBack' => 2,
            'weeksAhead' => 4,
            'restrictWeekdays' => false,
            'allowedWeekdays' => null,
            'addBookableUntil' => $addBookableUntil,
            'bookableUntil' => $bookableUntil,
        ]);
    }
}
