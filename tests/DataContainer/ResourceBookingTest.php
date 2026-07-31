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

namespace Markocupic\ResourceBookingBundle\Tests\DataContainer;

use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBooking;
use PHPUnit\Framework\TestCase;

class ResourceBookingTest extends TestCase
{
    public function testGetRbbModulesMapsIdToName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                'SELECT * FROM tl_module WHERE type = ?',
                [ResourceBookingWeekcalendarController::TYPE],
                $this->anything(),
            )
            ->willReturn([
                ['id' => 1, 'name' => 'Calendar A'],
                ['id' => 2, 'name' => 'Calendar B'],
            ])
        ;

        $result = (new ResourceBooking($connection))->getRbbModules();

        $this->assertSame([1 => 'Calendar A', 2 => 'Calendar B'], $result);
    }

    public function testGetRbbModulesReturnsEmptyArrayWhenNoModulesExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        $this->assertSame([], (new ResourceBooking($connection))->getRbbModules());
    }
}
