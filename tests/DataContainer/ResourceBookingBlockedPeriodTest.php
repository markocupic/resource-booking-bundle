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

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingBlockedPeriod;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingBlockedPeriodTest extends ContaoTestCase
{
    public function testResourceOptionsAreGroupedByResourceType(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 1, 'title' => 'Werkraum', 'typeTitle' => 'Räume'],
                ['id' => 2, 'title' => 'Turnhalle', 'typeTitle' => 'Räume'],
                ['id' => 3, 'title' => 'Notebooks', 'typeTitle' => 'Computer'],
            ])
        ;

        $dca = new ResourceBookingBlockedPeriod($this->createContaoFrameworkStub(), $connection, $this->translator());

        $this->assertSame(
            ['Räume' => [1 => 'Werkraum', 2 => 'Turnhalle'], 'Computer' => [3 => 'Notebooks']],
            $dca->getResourceOptions(),
        );
    }

    public function testValidBlockedTimesAreAccepted(): void
    {
        $dca = new ResourceBookingBlockedPeriod($this->createContaoFrameworkStub(), $this->createStub(Connection::class), $this->translator());

        $value = serialize([
            ['weekday' => 'saturday', 'timeFrom' => '', 'timeTo' => ''],
            ['weekday' => 'friday', 'timeFrom' => '14:00', 'timeTo' => '24:00'],
            ['weekday' => 'daily', 'timeFrom' => '7:30', 'timeTo' => ''],
        ]);

        $this->assertSame($value, $dca->validateBlockedTimes($value));
        $this->assertSame([], $dca->validateBlockedTimes([]));
    }

    public function testInvalidBlockedTimeIsRejected(): void
    {
        $dca = new ResourceBookingBlockedPeriod($this->createContaoFrameworkStub(), $this->createStub(Connection::class), $this->translator());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RBB.BE.invalidBlockedTime:2');

        $dca->validateBlockedTimes([
            ['weekday' => 'monday', 'timeFrom' => '08:00', 'timeTo' => '09:00'],
            ['weekday' => 'monday', 'timeFrom' => '25:00', 'timeTo' => ''],
        ]);
    }

    public function testTheEndTimeMustBeAfterTheStartTime(): void
    {
        $dca = new ResourceBookingBlockedPeriod($this->createContaoFrameworkStub(), $this->createStub(Connection::class), $this->translator());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RBB.BE.timeToBeforeTimeFrom:1');

        $dca->validateBlockedTimes([['weekday' => 'monday', 'timeFrom' => '12:00', 'timeTo' => '11:00']]);
    }

    public function testCountsFutureBookingsInTheBlockedPeriod(): void
    {
        $dateFrom = strtotime('today +7 days');
        $dateTo = strtotime('today +11 days');

        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                // In the period, resource type 4
                ['startTime' => $dateFrom + 8 * 3600, 'endTime' => $dateFrom + 9 * 3600, 'id' => 3, 'pid' => 4],
                // In the period, but another resource type
                ['startTime' => $dateFrom + 8 * 3600, 'endTime' => $dateFrom + 9 * 3600, 'id' => 5, 'pid' => 6],
            ])
        ;

        $period = [
            'published' => '1',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'blockedTimes' => null,
            'scope' => 'resourceTypes',
            'resourceTypes' => serialize(['4']),
        ];

        $dca = new ResourceBookingBlockedPeriod($this->createContaoFrameworkStub(), $connection, $this->translator());

        $this->assertSame(1, $dca->countFutureBookingsInBlockedPeriod($period));
        $this->assertSame(0, $dca->countFutureBookingsInBlockedPeriod(['published' => ''] + $period));
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id, array $parameters = []): string => $id.($parameters ? ':'.implode(',', $parameters) : ''))
        ;

        return $translator;
    }
}
