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

namespace Markocupic\ResourceBookingBundle\Tests\Api\Output;

use Markocupic\ResourceBookingBundle\Api\Output\BookingPreview;
use Markocupic\ResourceBookingBundle\Api\Output\BookingResult;
use Markocupic\ResourceBookingBundle\Api\Output\CancelResult;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testBookingPreview(): void
    {
        $this->assertSame(['isBookable' => true, 'slots' => [['timeSlotId' => 4]]], (new BookingPreview(true, [['timeSlotId' => 4]]))->toArray());
        $this->assertSame(['isBookable' => false, 'slots' => []], (new BookingPreview(false))->toArray());
    }

    /**
     * Only IDs and times of the saved bookings, not the complete rows.
     */
    public function testBookingResultFromBookingRows(): void
    {
        $result = BookingResult::fromBookingRows([
            ['id' => '10', 'pid' => 3, 'member' => 7, 'timeSlotId' => '4', 'startTime' => '1000', 'endTime' => '2000', 'phone' => '079'],
        ]);

        $this->assertSame(['isBooked' => true, 'bookings' => [['id' => 10, 'timeSlotId' => 4, 'startTime' => 1000, 'endTime' => 2000]]], $result->toArray());
        $this->assertSame(['isBooked' => false, 'bookings' => []], (new BookingResult(false))->toArray());
    }

    public function testCancelResult(): void
    {
        $this->assertSame(['isCancelled' => true, 'cancelledBookingIds' => [10, 11]], (new CancelResult(true, [10, 11]))->toArray());
        $this->assertSame(['isCancelled' => false, 'cancelledBookingIds' => []], (new CancelResult(false))->toArray());
    }
}
