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

namespace Markocupic\ResourceBookingBundle\Tests\Exception;

use Markocupic\ResourceBookingBundle\Exception\FieldValidationException;
use Markocupic\ResourceBookingBundle\Exception\StopBookingProcessException;
use PHPUnit\Framework\TestCase;

class FieldValidationExceptionTest extends TestCase
{
    public function testIsAStopBookingProcessException(): void
    {
        // Existing code that catches StopBookingProcessException keeps working
        $this->assertInstanceOf(StopBookingProcessException::class, FieldValidationException::forField('postal', 'Invalid'));
    }

    public function testForField(): void
    {
        $e = FieldValidationException::forField('postal', 'Bitte geben Sie eine gültige Postleitzahl ein.');

        $this->assertSame(['postal' => 'Bitte geben Sie eine gültige Postleitzahl ein.'], $e->getFieldErrors());
        $this->assertSame('Bitte geben Sie eine gültige Postleitzahl ein.', $e->getMessage());
    }

    public function testSeveralFieldsAndDefaultMessage(): void
    {
        $e = new FieldValidationException(['email' => 'E-Mail fehlt.', 'phone' => 'Telefon fehlt.']);

        $this->assertSame(['email', 'phone'], array_keys($e->getFieldErrors()));
        // Without a message the first field message is used
        $this->assertSame('E-Mail fehlt.', $e->getMessage());
    }

    public function testRequiresAtLeastOneFieldError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FieldValidationException([]);
    }
}
