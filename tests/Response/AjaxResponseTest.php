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

namespace Markocupic\ResourceBookingBundle\Tests\Response;

use Markocupic\ResourceBookingBundle\Api\Output\BookingResult;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\Response\MessageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AjaxResponseTest extends TestCase
{
    public function testTheJsonOfAnEmptyResponse(): void
    {
        $expected = [
            'status' => null,
            'messages' => [
                'warning' => null,
                'error' => null,
                'confirm' => null,
                'info' => null,
            ],
            'fieldErrors' => [],
            'data' => [],
            'action' => 'showBookingForm',
        ];

        $response = new AjaxResponse('showBookingForm');

        $this->assertSame($expected, $response->toArray());
        $this->assertSame(json_encode($expected), json_encode($response));
    }

    public function testTheJsonContainsStatusMessagesFieldErrorsAndData(): void
    {
        $response = new AjaxResponse('bookingRequest');
        $response->setStatus(AjaxStatus::Warning);
        $response->setWarningMessage('Bitte prüfen');
        $response->setFieldError('email', 'Ungültige E-Mail-Adresse');
        $response->setData('isBooked', false);

        $this->assertSame(
            [
                'status' => 'warning',
                'messages' => ['warning' => 'Bitte prüfen', 'error' => null, 'confirm' => null, 'info' => null],
                'fieldErrors' => ['email' => 'Ungültige E-Mail-Adresse'],
                'data' => ['isBooked' => false],
                'action' => 'bookingRequest',
            ],
            $response->toArray(),
        );
    }

    public function testFieldErrors(): void
    {
        $response = new AjaxResponse('bookingRequest');
        $this->assertFalse($response->hasFieldErrors());

        $response->setFieldError('postal', 'Bitte geben Sie eine gültige Postleitzahl ein.');
        $response->setFieldError('email', 'Bitte geben Sie eine gültige E-Mail-Adresse ein.');

        $this->assertTrue($response->hasFieldErrors());
        $this->assertSame(['postal', 'email'], array_keys($response->getFieldErrors()));
    }

    public function testAction(): void
    {
        $response = new AjaxResponse('initialAction');
        $this->assertSame('initialAction', $response->getAction());

        $response->setAction('changedAction');
        $this->assertSame('changedAction', $response->getAction());
    }

    #[DataProvider('provideStatuses')]
    public function testStatus(AjaxStatus $status, string $json): void
    {
        $response = new AjaxResponse('action');
        $this->assertNull($response->getStatus());

        $response->setStatus($status);

        $this->assertSame($status, $response->getStatus());
        $this->assertSame($json, $response->toArray()['status']);
    }

    #[DataProvider('provideMessageTypes')]
    public function testMessages(MessageType $type, string $setter, string $getter, string $checker): void
    {
        $response = new AjaxResponse('action');

        $this->assertFalse($response->hasMessage($type));
        $this->assertNull($response->getMessage($type));

        $response->{$setter}('Some message');

        $this->assertTrue($response->hasMessage($type));
        $this->assertTrue($response->{$checker}());
        $this->assertSame('Some message', $response->{$getter}());
        $this->assertSame('Some message', $response->toArray()['messages'][$type->value]);

        $response->removeMessage($type);

        $this->assertFalse($response->{$checker}());
        $this->assertNull($response->{$getter}());
    }

    public function testAnEmptyMessageCountsAsNoMessage(): void
    {
        $response = new AjaxResponse('action');
        $response->setErrorMessage('');

        $this->assertFalse($response->hasErrorMessage());
        $this->assertSame('', $response->getErrorMessage());
    }

    public function testAnErrorReplacesTheInfoAndConfirmationMessages(): void
    {
        $response = new AjaxResponse('action');
        $response->setErrorMessage('Boom');
        $response->setInfoMessage('Info');
        $response->setConfirmationMessage('Confirm');
        $response->setWarningMessage('Warning');

        $this->assertSame(
            ['warning' => 'Warning', 'error' => 'Boom', 'confirm' => null, 'info' => null],
            $response->toArray()['messages'],
        );

        // The messages themselves are kept
        $this->assertSame('Info', $response->getInfoMessage());
        $this->assertSame('Confirm', $response->getConfirmationMessage());
    }

    public function testInfoAndConfirmationMessagesAreSentWithoutError(): void
    {
        $response = new AjaxResponse('action');
        $response->setInfoMessage('Info');
        $response->setConfirmationMessage('Confirm');

        $this->assertSame(
            ['warning' => null, 'error' => null, 'confirm' => 'Confirm', 'info' => 'Info'],
            $response->toArray()['messages'],
        );
    }

    public function testSetDataAndGetData(): void
    {
        $response = new AjaxResponse('action');

        $this->assertNull($response->getData('missing'));

        $response->setData('bookings', ['a', 'b']);
        // Listeners of the AjaxResponseEvent store booleans/scalars too
        $response->setData('isSpecial', true);
        $response->setData('count', 5);

        $this->assertSame(['a', 'b'], $response->getData('bookings'));
        $this->assertTrue($response->getData('isSpecial'));
        $this->assertSame(5, $response->getData('count'));
        $this->assertSame(['bookings' => ['a', 'b'], 'isSpecial' => true, 'count' => 5], $response->getAllData());
    }

    public function testSetResultAddsTheDataOfTheResult(): void
    {
        $response = new AjaxResponse('bookingRequest');
        $response->setData('notice', 'kept');

        $response->setResult(new BookingResult(true, [['id' => 1, 'timeSlotId' => 2, 'startTime' => 3, 'endTime' => 4]]));

        $this->assertSame('kept', $response->getData('notice'));
        $this->assertTrue($response->getData('isBooked'));
        $this->assertSame([['id' => 1, 'timeSlotId' => 2, 'startTime' => 3, 'endTime' => 4]], $response->getData('bookings'));
    }

    public function testRetainDataKeepsOnlyTheGivenKeys(): void
    {
        $response = new AjaxResponse('action');
        $response->mergeData(['html' => '<div>', 'rows' => [1], 'id' => 3]);

        $response->retainData(['html', 'id', 'missing']);

        $this->assertSame(['html' => '<div>', 'id' => 3], $response->getAllData());
    }

    public function testMergeDataOverwritesExistingKeys(): void
    {
        $response = new AjaxResponse('action');
        $response->setData('a', [1]);
        $response->setData('b', [2]);

        $response->mergeData([
            'b' => [22],
            'c' => [3],
        ]);

        $this->assertSame(['a' => [1], 'b' => [22], 'c' => [3]], $response->toArray()['data']);
    }

    /**
     * @return iterable<string, array{AjaxStatus, string}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'error' => [AjaxStatus::Error, 'error'];
        yield 'success' => [AjaxStatus::Success, 'success'];
        yield 'warning' => [AjaxStatus::Warning, 'warning'];
    }

    /**
     * @return iterable<string, array{MessageType, string, string, string}>
     */
    public static function provideMessageTypes(): iterable
    {
        yield 'confirm' => [MessageType::Confirm, 'setConfirmationMessage', 'getConfirmationMessage', 'hasConfirmationMessage'];
        yield 'error' => [MessageType::Error, 'setErrorMessage', 'getErrorMessage', 'hasErrorMessage'];
        yield 'info' => [MessageType::Info, 'setInfoMessage', 'getInfoMessage', 'hasInfoMessage'];
        yield 'warning' => [MessageType::Warning, 'setWarningMessage', 'getWarningMessage', 'hasWarningMessage'];
    }
}
