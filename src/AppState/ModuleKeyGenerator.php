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

namespace Markocupic\ResourceBookingBundle\AppState;

use Symfony\Component\HttpFoundation\Request;

/**
 * Generates the module key that identifies a week calendar module on the page
 * ("<module id>_<index>", e.g. "33_0" for the first and "33_1" for the second
 * week calendar). It allows several week calendars on the same page: the key is
 * part of the access token of the API and of the IDs in the template.
 *
 * The counter is stored in the attributes of the main request (the modules are
 * rendered as fragments, i.e. sub requests) and therefore starts again with
 * every request, also in worker mode.
 */
class ModuleKeyGenerator
{
    private const COUNTER_ATTRIBUTE = '_rbb_module_index';

    public function generate(Request $mainRequest, int $moduleId): string
    {
        $index = (int) $mainRequest->attributes->get(self::COUNTER_ATTRIBUTE, -1) + 1;
        $mainRequest->attributes->set(self::COUNTER_ATTRIBUTE, $index);

        return $moduleId.'_'.$index;
    }
}
