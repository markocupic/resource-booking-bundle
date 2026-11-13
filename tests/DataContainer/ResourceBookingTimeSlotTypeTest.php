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

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\DataContainer;
use Contao\Message;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\ResourceBookingTimeSlotType;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResourceBookingTimeSlotTypeTest extends ContaoTestCase
{
    public function testDeletionIsAllowedWithoutAnId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchOne')
        ;

        $dc = $this->createClassWithPropertiesStub(DataContainer::class, ['id' => 0]);

        (new ResourceBookingTimeSlotType($this->createContaoFrameworkStub(), $connection, $this->translator()))->preventDeletionWithFutureBookings($dc);
    }

    public function testDeletionIsAllowedWithoutFutureBookings(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('endTime > ?'))
            ->willReturn(0)
        ;

        // Neither bookings nor time slots are deleted by the callback: Contao deletes
        // the time slots (ctable), past bookings are kept.
        $connection
            ->expects($this->never())
            ->method('executeStatement')
        ;

        $dc = $this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]);

        (new ResourceBookingTimeSlotType($this->createContaoFrameworkStub(), $connection, $this->translator()))->preventDeletionWithFutureBookings($dc);
    }

    public function testDeletionIsBlockedWithFutureBookings(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(5)
        ;

        $messageAdapter = $this->createAdapterMock(['addError']);
        $messageAdapter
            ->expects($this->once())
            ->method('addError')
            ->with('RBB.BE.deleteTimeSlotTypeBlocked:5')
        ;

        $framework = $this->createContaoFrameworkStub([
            Message::class => $messageAdapter,
            System::class => $this->createConfiguredAdapterStub(['getReferer' => 'https://example.com/contao']),
        ]);

        $dc = $this->createClassWithPropertiesStub(DataContainer::class, ['id' => 7]);

        $this->expectException(RedirectResponseException::class);

        (new ResourceBookingTimeSlotType($framework, $connection, $this->translator()))->preventDeletionWithFutureBookings($dc);
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
