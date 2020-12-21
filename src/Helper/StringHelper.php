<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Helper;

/**
 * Class StringHelper
 * @package Markocupic\ResourceBookingBundle\Helper
 */
class StringHelper
{
    public function toSnakeCase(string $str, string $glue = '_'): ?string
    {
        return preg_replace_callback(
            '/[A-Z]/',
            static function ($matches) use ($glue) {
                return $glue.strtolower($matches[0]);
            },
            $str
        );
    }
}
