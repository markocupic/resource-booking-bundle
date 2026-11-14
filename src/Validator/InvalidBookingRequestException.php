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

namespace Markocupic\ResourceBookingBundle\Validator;

final class InvalidBookingRequestException extends \RuntimeException
{
    public const FIELD_NOT_PERMITTED = 'RBB.ERR.invalidFormFieldSubmitted';

    public const INVALID_VALUE = 'RBB.ERR.invalidFormValueSubmitted';

    private function __construct(
        private readonly string $translationKey,
        private readonly string $field,
    ) {
        parent::__construct(\sprintf('%s: %s', $translationKey, $field));
    }

    public static function fieldNotPermitted(string $field): self
    {
        return new self(self::FIELD_NOT_PERMITTED, $field);
    }

    public static function invalidValue(string $field): self
    {
        return new self(self::INVALID_VALUE, $field);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
