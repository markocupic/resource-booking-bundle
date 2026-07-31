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

namespace Markocupic\ResourceBookingBundle\Tests\EventListener\ContaoHooks;

use Contao\Controller;
use Contao\TestCase\ContaoTestCase;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\RegExpListener;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegExpListenerTest extends ContaoTestCase
{
    private const START_ERROR = 'RBB.MSG.pleaseInsertValidBookingStartTime';

    private const END_ERROR = 'RBB.MSG.pleaseInsertValidBookingEndTime';

    public function testReturnsFalseForAnUnrelatedRegexp(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->never())
            ->method('addError')
        ;

        $listener = $this->createListener(isValidBookingTime: true);

        $this->assertFalse($listener->onCustomRegexp('somethingElse', '08:00', $widget));
    }

    public function testValidStartTimeAddsNoError(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->never())
            ->method('addError')
        ;

        $listener = $this->createListener(isValidBookingTime: true);

        $this->assertTrue(
            $listener->onCustomRegexp(RegExpListener::REGEX_RESOURCE_BOOKING_START_TIME, '08:00', $widget),
        );
    }

    public function testInvalidStartTimeFormatAddsError(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->once())
            ->method('addError')
            ->with(self::START_ERROR)
        ;

        $listener = $this->createListener(isValidBookingTime: true);

        $this->assertTrue(
            $listener->onCustomRegexp(RegExpListener::REGEX_RESOURCE_BOOKING_START_TIME, '99:99', $widget),
        );
    }

    public function testStartTimeOutsideAllowedBookingWindowAddsError(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->once())
            ->method('addError')
            ->with(self::START_ERROR)
        ;

        // Format is valid, but the booking time is rejected by the DateHelper.
        $listener = $this->createListener(isValidBookingTime: false);

        $this->assertTrue(
            $listener->onCustomRegexp(RegExpListener::REGEX_RESOURCE_BOOKING_START_TIME, '08:00', $widget),
        );
    }

    public function testStartTimeFailingBothChecksAddsTwoErrors(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->exactly(2))
            ->method('addError')
            ->with(self::START_ERROR)
        ;

        $listener = $this->createListener(isValidBookingTime: false);

        $this->assertTrue(
            $listener->onCustomRegexp(RegExpListener::REGEX_RESOURCE_BOOKING_START_TIME, '99:99', $widget),
        );
    }

    public function testValidEndTimeAddsNoError(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->never())
            ->method('addError')
        ;

        $listener = $this->createListener(isValidBookingTime: true);

        $this->assertTrue(
            $listener->onCustomRegexp(RegExpListener::REGEX_RESOURCE_BOOKING_END_TIME, '10:00', $widget),
        );
    }

    public function testInvalidEndTimeFormatAddsError(): void
    {
        $widget = $this->createMock(Widget::class);
        $widget
            ->expects($this->once())
            ->method('addError')
            ->with(self::END_ERROR)
        ;

        $listener = $this->createListener(isValidBookingTime: true);

        $this->assertTrue(
            $listener->onCustomRegexp(RegExpListener::REGEX_RESOURCE_BOOKING_END_TIME, '99:99', $widget),
        );
    }

    private function createListener(bool $isValidBookingTime): RegExpListener
    {
        $controllerAdapter = $this->mockAdapter(['loadLanguageFile']);

        $dateHelperAdapter = $this->mockAdapter(['isValidBookingTime']);
        $dateHelperAdapter
            ->method('isValidBookingTime')
            ->willReturn($isValidBookingTime)
        ;

        $framework = $this->mockContaoFramework([
            Controller::class => $controllerAdapter,
            DateHelper::class => $dateHelperAdapter,
        ]);

        // Translator echoes the message key so assertions can match on it.
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id)
        ;

        return new RegExpListener($framework, $translator, Validation::createValidator());
    }
}
