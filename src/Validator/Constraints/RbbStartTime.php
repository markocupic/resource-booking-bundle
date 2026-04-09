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
class RbbStartTime extends Regex
{
    public function __construct(array|null $options = null)
    {
        parent::__construct([
            'pattern' => '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            'message' => 'Please enter the start time in the format HH:MM. Allowed values are 00:00 to 23:59.',
            'normalizer' => 'trim',
            ...($options ?? []),
        ]);
    }

    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}
