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
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;

class WeekBoundariesTest extends ContaoTestCase
{
    /**
     * Wednesday; the current week starts on monday 2030-10-14.
     */
    private const NOW = '2030-10-16 12:00';

    public function testWithoutResourceTheDefaultsApply(): void
    {
        $now = strtotime(self::NOW);

        $this->assertSame(strtotime(\sprintf('2030-10-14 -%d weeks', RbbConfig::RBB_DEFAULT_WEEKS_BACK)), (new WeekBoundaries())->getFirstPermittedWeek('monday', null, $now));
        $this->assertSame(strtotime(\sprintf('2030-10-14 +%d weeks', RbbConfig::RBB_DEFAULT_WEEKS_AHEAD)), (new WeekBoundaries())->getLastPermittedWeek('monday', null, $now));
    }

    public function testTheResourceDefinesTheWeeksBackAndAhead(): void
    {
        $resource = $this->resource();

        $this->assertSame(strtotime('2030-09-30'), (new WeekBoundaries())->getFirstPermittedWeek('monday', $resource, strtotime(self::NOW)));
        $this->assertSame(strtotime('2030-11-11'), (new WeekBoundaries())->getLastPermittedWeek('monday', $resource, strtotime(self::NOW)));
    }

    public function testTheWeeksStartOnTheFirstDayOfTheTimetable(): void
    {
        $resource = $this->resource();

        // The current week starts on sunday 2030-10-13
        $this->assertSame(strtotime('2030-09-29'), (new WeekBoundaries())->getFirstPermittedWeek('sunday', $resource, strtotime(self::NOW)));
        $this->assertSame(strtotime('2030-11-10'), (new WeekBoundaries())->getLastPermittedWeek('sunday', $resource, strtotime(self::NOW)));
    }

    public function testBookableUntilBeforeTheWeeksAheadLimitDefinesTheLastWeek(): void
    {
        $resource = $this->resource(bookableUntil: '2030-10-30');

        $this->assertSame(strtotime('2030-10-28'), (new WeekBoundaries())->getLastPermittedWeek('monday', $resource, strtotime(self::NOW)));
    }

    public function testBookableUntilAfterTheWeeksAheadLimitIsIgnored(): void
    {
        $resource = $this->resource(bookableUntil: '2031-06-01');

        $this->assertSame(strtotime('2030-11-11'), (new WeekBoundaries())->getLastPermittedWeek('monday', $resource, strtotime(self::NOW)));
    }

    public function testBookableUntilInThePastClampsToTheCurrentWeek(): void
    {
        $resource = $this->resource(bookableUntil: '2030-09-01');

        $this->assertSame(strtotime('2030-10-14'), (new WeekBoundaries())->getLastPermittedWeek('monday', $resource, strtotime(self::NOW)));
    }

    /**
     * 2 weeks back, 4 weeks ahead.
     */
    private function resource(string|null $bookableUntil = null): ResourceBookingResourceModel
    {
        return $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, [
            'weeksBack' => 2,
            'weeksAhead' => 4,
            'addBookableUntil' => null !== $bookableUntil,
            'bookableUntil' => null === $bookableUntil ? '' : (string) strtotime($bookableUntil),
        ]);
    }
}
