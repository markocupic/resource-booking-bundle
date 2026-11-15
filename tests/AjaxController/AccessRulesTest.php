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

namespace Markocupic\ResourceBookingBundle\Tests\AjaxController;

use Contao\ModuleModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AjaxController\AbstractController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;

class AccessRulesTest extends ContaoTestCase
{
    public function testOnlyResourcesOfTheResourceTypesOfTheModuleAreAvailable(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 1, 'resourceBooking_resourceTypes' => serialize(['3', '4'])]);

        $this->assertTrue(AbstractController::isResourceOfModule($this->resourceOfType(3), $module));
        $this->assertTrue(AbstractController::isResourceOfModule($this->resourceOfType(4), $module));
        $this->assertFalse(AbstractController::isResourceOfModule($this->resourceOfType(5), $module));
    }

    public function testNoResourceIsAvailableInAModuleWithoutResourceTypes(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 1, 'resourceBooking_resourceTypes' => null]);

        $this->assertFalse(AbstractController::isResourceOfModule($this->resourceOfType(3), $module));
    }

    public function testBookingsCanBeCanceledUntilTheirEnd(): void
    {
        $now = 1_800_000_000;

        $this->assertTrue(CancelController::isCancelableByTime(['endTime' => $now + 3600], $now));
        $this->assertTrue(CancelController::isCancelableByTime(['endTime' => $now], $now));
        $this->assertFalse(CancelController::isCancelableByTime(['endTime' => $now - 1], $now));
        $this->assertFalse(CancelController::isCancelableByTime([], $now));
    }

    private function resourceOfType(int $resourceTypeId): ResourceBookingResourceModel
    {
        return $this->createClassWithPropertiesStub(ResourceBookingResourceModel::class, ['id' => 10 + $resourceTypeId, 'pid' => $resourceTypeId]);
    }
}
