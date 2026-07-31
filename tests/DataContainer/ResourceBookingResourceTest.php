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
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingResource;
use PHPUnit\Framework\TestCase;

class ResourceBookingResourceTest extends TestCase
{
    public function testChildRecordCallbackRendersTheTitle(): void
    {
        $dca = new ResourceBookingResource($this->createMock(Connection::class));

        $this->assertSame(
            '<div class="tl_content_left">Room 1</div>',
            $dca->childRecordCallback(['title' => 'Room 1']),
        );
    }
}
