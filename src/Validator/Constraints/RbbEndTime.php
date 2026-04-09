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

namespace Markocupic\ResourceBookingBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class RbbEndTime extends Regex
{
    public function __construct(array|null $options = null)
    {
        parent::__construct([
            'pattern' => '/^(?:(?:0[1-9]|1\d|2[0-3]):[0-5]\d|00:[0-5][1-9]|24:00)$/',
            'message' => 'Please enter the end time in the format HH:MM. Allowed values are 00:01 to 24:00.',
            'normalizer' => 'trim',
            ...($options ?? []),
        ]);
    }

    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}
