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

namespace Markocupic\ResourceBookingBundle\Exception;

/**
 * Aborts the booking process because of invalid input in one or more form
 * fields. The messages are sent to the front end per field (fieldErrors): the
 * booking window lists them and links each message to its field (aria-invalid,
 * aria-describedby).
 *
 * Can be thrown e.g. in a listener of the PreBookingEvent:
 *
 *     throw FieldValidationException::forField('postal', 'Bitte geben Sie eine gültige Postleitzahl ein.');
 *
 *     throw new FieldValidationException(['email' => '…', 'phone' => '…']);
 */
class FieldValidationException extends StopBookingProcessException
{
    /**
     * @param array<string, string> $fieldErrors Field name (as in the name attribute of the input) => message
     */
    public function __construct(
        private readonly array $fieldErrors,
        string $message = '',
        \Throwable|null $previous = null,
    ) {
        if ([] === $fieldErrors) {
            throw new \InvalidArgumentException('At least one field error is required.');
        }

        parent::__construct('' !== $message ? $message : (string) reset($fieldErrors), 0, $previous);
    }

    public static function forField(string $field, string $message, \Throwable|null $previous = null): self
    {
        return new self([$field => $message], $message, $previous);
    }

    /**
     * @return array<string, string>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
