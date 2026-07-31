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

use Contao\DataContainer;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\Member;

class MemberTest extends ContaoTestCase
{
    public function testDeletesChildBookingsWhenMemberHasAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('tl_resource_booking', ['member' => 5])
        ;

        $dc = $this->mockClassWithProperties(DataContainer::class, ['id' => 5]);

        (new Member($connection))->deleteChildRecords($dc);
    }

    public function testDoesNothingWhenMemberHasNoId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('delete')
        ;

        $dc = $this->mockClassWithProperties(DataContainer::class, ['id' => 0]);

        (new Member($connection))->deleteChildRecords($dc);
    }
}
