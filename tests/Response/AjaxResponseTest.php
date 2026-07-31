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

use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AjaxResponseTest extends TestCase
{
    public function testConstructorInitializesTheDataStructure(): void
    {
        $response = new AjaxResponse('showBookingForm');

        $this->assertSame(
            [
                'status' => null,
                'messages' => [
                    'warning' => null,
                    'error' => null,
                    'confirm' => null,
                    'info' => null,
                ],
                'data' => [],
                'action' => 'showBookingForm',
            ],
            $response->getAll(),
        );
    }

    public function testActionRoundTrip(): void
    {
        $response = new AjaxResponse('initialAction');

        $this->assertSame('initialAction', $response->getAction());

        $response->setAction('changedAction');

        $this->assertSame('changedAction', $response->getAction());
    }

    #[DataProvider('provideValidStatuses')]
    public function testSetAndGetValidStatus(string $status): void
    {
        $response = new AjaxResponse('action');

        $this->assertNull($response->getStatus());

        $response->setStatus($status);

        $this->assertSame($status, $response->getStatus());
    }

    public function testSetStatusThrowsOnInvalidValue(): void
    {
        $response = new AjaxResponse('action');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('can not be "bogus"');

        $response->setStatus('bogus');
    }

    #[DataProvider('provideMessageAccessors')]
    public function testMessageRoundTrip(string $setter, string $getter, string $checker, string $deleter): void
    {
        $response = new AjaxResponse('action');

        $this->assertFalse($response->{$checker}());
        $this->assertNull($response->{$getter}());

        $response->{$setter}('Some message');

        $this->assertTrue($response->{$checker}());
        $this->assertSame('Some message', $response->{$getter}());

        $response->{$deleter}();

        $this->assertFalse($response->{$checker}());
        $this->assertNull($response->{$getter}());
    }

    public function testHasMessageTreatsEmptyStringAsAbsent(): void
    {
        $response = new AjaxResponse('action');
        $response->setErrorMessage('');

        // hasErrorMessage() uses !empty(), so an empty string counts as "no message".
        $this->assertFalse($response->hasErrorMessage());
        $this->assertSame('', $response->getErrorMessage());
    }

    public function testPrepareBeforeSendReturnsSelf(): void
    {
        $response = new AjaxResponse('action');

        $this->assertSame($response, $response->prepareBeforeSend());
        $this->assertSame($response, $response->prepareBeforeSend(true));
    }

    public function testPrepareBeforeSendRemovesInfoAndConfirmationWhenAnErrorIsPresent(): void
    {
        $response = new AjaxResponse('action');
        $response->setErrorMessage('Boom');
        $response->setInfoMessage('Info');
        $response->setConfirmationMessage('Confirm');
        $response->setWarningMessage('Warning');

        $response->prepareBeforeSend(true);

        // Info and confirmation are cleared ...
        $this->assertNull($response->getInfoMessage());
        $this->assertNull($response->getConfirmationMessage());
        // ... while error and warning survive.
        $this->assertSame('Boom', $response->getErrorMessage());
        $this->assertSame('Warning', $response->getWarningMessage());
    }

    public function testPrepareBeforeSendKeepsMessagesWhenThereIsNoError(): void
    {
        $response = new AjaxResponse('action');
        $response->setInfoMessage('Info');
        $response->setConfirmationMessage('Confirm');

        $response->prepareBeforeSend(true);

        $this->assertSame('Info', $response->getInfoMessage());
        $this->assertSame('Confirm', $response->getConfirmationMessage());
    }

    public function testPrepareBeforeSendKeepsMessagesWhenFlagIsFalse(): void
    {
        $response = new AjaxResponse('action');
        $response->setErrorMessage('Boom');
        $response->setInfoMessage('Info');
        $response->setConfirmationMessage('Confirm');

        $response->prepareBeforeSend(false);

        $this->assertSame('Info', $response->getInfoMessage());
        $this->assertSame('Confirm', $response->getConfirmationMessage());
    }

    public function testSetDataAndGetData(): void
    {
        $response = new AjaxResponse('action');

        $this->assertNull($response->getData('missing'));

        $response->setData('bookings', ['a', 'b']);

        $this->assertSame(['a', 'b'], $response->getData('bookings'));
    }

    public function testSetDataAndGetDataSupportScalarValues(): void
    {
        $response = new AjaxResponse('action');

        // The controllers store booleans/scalars too, e.g. setData('bookingProcessSucceeded', true).
        $response->setData('bookingProcessSucceeded', true);
        $response->setData('count', 5);

        $this->assertTrue($response->getData('bookingProcessSucceeded'));
        $this->assertSame(5, $response->getData('count'));
    }

    public function testSetDataFromArrayMergesWithExistingData(): void
    {
        $response = new AjaxResponse('action');
        $response->setData('a', [1]);
        $response->setData('b', [2]);

        $response->setDataFromArray([
            'b' => [22], // overwrites
            'c' => [3], // added
        ]);

        $this->assertSame([1], $response->getData('a'));
        $this->assertSame([22], $response->getData('b'));
        $this->assertSame([3], $response->getData('c'));

        $this->assertSame(
            ['a' => [1], 'b' => [22], 'c' => [3]],
            $response->getAll()['data'],
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideValidStatuses(): iterable
    {
        return [
            'error' => [AjaxResponse::STATUS_ERROR],
            'success' => [AjaxResponse::STATUS_SUCCESS],
            'warning' => [AjaxResponse::STATUS_WARNING],
        ];
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function provideMessageAccessors(): iterable
    {
        return [
            'error' => ['setErrorMessage', 'getErrorMessage', 'hasErrorMessage', 'deleteErrorMessage'],
            'warning' => ['setWarningMessage', 'getWarningMessage', 'hasWarningMessage', 'deleteWarningMessage'],
            'info' => ['setInfoMessage', 'getInfoMessage', 'hasInfoMessage', 'deleteInfoMessage'],
            'confirm' => ['setConfirmationMessage', 'getConfirmationMessage', 'hasConfirmationMessage', 'deleteConfirmationMessage'],
        ];
    }
}
