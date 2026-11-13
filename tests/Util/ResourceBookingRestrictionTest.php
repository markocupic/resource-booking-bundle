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
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;

class ResourceBookingRestrictionTest extends ContaoTestCase
{
    public function testAllWeekdaysAreAllowedWithoutResource(): void
    {
        $this->assertTrue(ResourceBookingRestriction::isWeekdayAllowed(null, 'sunday'));
    }

    public function testAllWeekdaysAreAllowedIfRestrictionIsDisabled(): void
    {
        $row = [
            'restrictWeekdays' => '',
            'allowedWeekdays' => serialize(['monday']),
        ];

        $this->assertTrue(ResourceBookingRestriction::isWeekdayAllowed($row, 'tuesday'));
    }

    public function testOnlySelectedWeekdaysAreAllowedIfRestrictionIsEnabled(): void
    {
        $row = [
            'restrictWeekdays' => '1',
            'allowedWeekdays' => serialize(['monday', 'wednesday']),
        ];

        $this->assertTrue(ResourceBookingRestriction::isWeekdayAllowed($row, 'monday'));
        $this->assertTrue(ResourceBookingRestriction::isWeekdayAllowed($row, 'Wednesday'));
        $this->assertFalse(ResourceBookingRestriction::isWeekdayAllowed($row, 'tuesday'));
    }

    public function testNoWeekdayIsAllowedIfRestrictionIsEnabledWithoutSelection(): void
    {
        $row = [
            'restrictWeekdays' => 1,
            'allowedWeekdays' => null,
        ];

        $this->assertFalse(ResourceBookingRestriction::isWeekdayAllowed($row, 'monday'));
    }

    public function testWeekdayRestrictionWorksWithModel(): void
    {
        $model = $this->createClassWithPropertiesMock(ResourceBookingResourceModel::class, [
            'restrictWeekdays' => true,
            'allowedWeekdays' => serialize(['friday']),
            'addBookableUntil' => false,
            'bookableUntil' => '',
        ]);

        $this->assertTrue(ResourceBookingRestriction::isWeekdayAllowed($model, 'friday'));
        $this->assertFalse(ResourceBookingRestriction::isWeekdayAllowed($model, 'monday'));
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
}
