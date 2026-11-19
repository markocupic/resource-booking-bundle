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
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;

class ResourceRulesTest extends ContaoTestCase
{
    public function testBookableUntilIsNullIfDisabledOrEmpty(): void
    {
        $this->assertNull((new ResourceRules())->bookableUntil);
        $this->assertNull(ResourceRules::fromRow(['addBookableUntil' => '', 'bookableUntil' => '1700000000'])->bookableUntil);
        $this->assertNull(ResourceRules::fromRow(['addBookableUntil' => '1', 'bookableUntil' => ''])->bookableUntil);
    }

    public function testBookableUntilReturnsTimestamp(): void
    {
        $this->assertSame(1700000000, ResourceRules::fromRow(['addBookableUntil' => '1', 'bookableUntil' => '1700000000'])->bookableUntil);
    }

    public function testWeeksBackAndAheadFallBackToTheDefaults(): void
    {
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_BACK, (new ResourceRules())->weeksBack);
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_AHEAD, (new ResourceRules())->weeksAhead);
        $this->assertSame(RbbConfig::RBB_DEFAULT_WEEKS_BACK, ResourceRules::fromRow(['weeksBack' => ''])->weeksBack);
    }

    public function testWeeksBackAndAheadAreReadFromTheResource(): void
    {
        $row = ['weeksBack' => '0', 'weeksAhead' => '12'];

        $this->assertSame(0, ResourceRules::fromRow($row)->weeksBack);
        $this->assertSame(12, ResourceRules::fromRow($row)->weeksAhead);
        $this->assertSame(0, ResourceRules::fromRow(['weeksAhead' => -3])->weeksAhead);
    }

    public function testAutoConfirmIsEnabledByDefault(): void
    {
        $this->assertTrue((new ResourceRules())->autoConfirm);
        $this->assertTrue(ResourceRules::fromRow([])->autoConfirm);
    }

    public function testAutoConfirmIsReadFromTheResource(): void
    {
        $this->assertTrue(ResourceRules::fromRow(['autoConfirm' => '1'])->autoConfirm);
        $this->assertFalse(ResourceRules::fromRow(['autoConfirm' => ''])->autoConfirm);
        $this->assertFalse(ResourceRules::fromRow(['autoConfirm' => 0])->autoConfirm);

        $model = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['autoConfirm' => false]);
        $this->assertFalse(ResourceRules::fromModel($model)->autoConfirm);
    }

    public function testReadsTheResourceModel(): void
    {
        $model = $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, [
            'id' => '3',
            'pid' => '4',
            'timeSlotType' => '5',
            'itemsAvailable' => '2',
            'weeksBack' => '1',
            'weeksAhead' => '8',
            'addBookableUntil' => '1',
            'bookableUntil' => '1700000000',
            'autoConfirm' => '1',
        ]);

        $this->assertEquals(new ResourceRules(3, 4, 5, 2, 1, 8, 1700000000, true), ResourceRules::fromModel($model));
    }

    public function testWithoutResourceTheDefaultsApply(): void
    {
        $this->assertEquals(new ResourceRules(), ResourceRules::fromModel(null));
    }
}
