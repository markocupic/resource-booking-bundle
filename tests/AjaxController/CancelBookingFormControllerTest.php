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

use Contao\FrontendUser;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AjaxController\CancelBookingFormController;
use Markocupic\ResourceBookingBundle\Booking\BookingRepository;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class CancelBookingFormControllerTest extends ContaoTestCase
{
    private const NOW = '2030-10-16 12:00';

    public function testOpensTheCancelWindowForAFutureBookingOfTheMember(): void
    {
        $response = $this->handle($this->booking(memberId: 5, startTime: strtotime('2030-10-20 08:00')), memberId: 5);

        $this->assertSame(AjaxStatus::Success, $response->getStatus());
        $this->assertSame(['id' => 12, 'bookingUuid' => 'uuid-12'], $response->getData('booking'));
    }

    public function testTheBookingOfAnotherMemberIsRefused(): void
    {
        $response = $this->handle($this->booking(memberId: 6, startTime: strtotime('2030-10-20 08:00')), memberId: 5);

        $this->assertSame(AjaxStatus::Error, $response->getStatus());
        $this->assertSame('RBB.ERR.notAuthorized', $response->getErrorMessage());
        $this->assertNull($response->getData('booking'));
    }

    public function testAPastBookingIsRefused(): void
    {
        $response = $this->handle($this->booking(memberId: 5, startTime: strtotime('2030-10-10 08:00')), memberId: 5);

        $this->assertSame(AjaxStatus::Error, $response->getStatus());
        $this->assertSame('RBB.ERR.cancelingPastBookingNotPossible', $response->getErrorMessage());
    }

    public function testGuestsAndUnknownBookingsAreRefused(): void
    {
        $this->assertSame(AjaxStatus::Error, $this->handle($this->booking(memberId: 5, startTime: strtotime('2030-10-20 08:00')), memberId: null)->getStatus());
        $this->assertSame(AjaxStatus::Error, $this->handle(null, memberId: 5)->getStatus());
        $this->assertSame(AjaxStatus::Error, $this->handle(null, memberId: 5, bookingId: 'abc')->getStatus());
    }

    private function handle(ResourceBookingModel|null $booking, int|null $memberId, string $bookingId = '12'): AjaxResponse
    {
        $user = $this->createStub(LoggedInFrontendUser::class);
        $user
            ->method('getLoggedInUser')
            ->willReturn(null === $memberId ? null : $this->createClassWithPropertiesStub(FrontendUser::class, ['id' => $memberId]))
        ;

        $repository = $this->createStub(BookingRepository::class);
        $repository
            ->method('findById')
            ->willReturn($booking)
        ;

        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnArgument(0)
        ;

        $translator
            ->method('getLocale')
            ->willReturn('de')
        ;

        $controller = new CancelBookingFormController(
            $this->createContaoFrameworkStub([System::class => $this->createAdapterStub(['loadLanguageFile'])]),
            $user,
            $translator,
            $repository,
            new MockClock(new \DateTimeImmutable(self::NOW)),
        );

        return $controller->generateResponse(new Request([], ['bookingId' => $bookingId]), new AjaxResponse(CancelBookingFormController::ACTION));
    }

    private function booking(int $memberId, int $startTime): ResourceBookingModel
    {
        $booking = $this->createClassWithPropertiesStub(ResourceBookingModel::class, [
            'id' => 12,
            'bookingUuid' => 'uuid-12',
            'member' => $memberId,
            'startTime' => $startTime,
            'endTime' => $startTime + 3600,
        ]);

        $booking
            ->method('row')
            ->willReturn(['id' => 12, 'bookingUuid' => 'uuid-12', 'member' => $memberId, 'startTime' => $startTime, 'endTime' => $startTime + 3600])
        ;

        return $booking;
    }
}
