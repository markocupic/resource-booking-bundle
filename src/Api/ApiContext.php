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

namespace Markocupic\ResourceBookingBundle\Api;

use Contao\ModuleModel;
use Contao\PageModel;

/**
 * Module and page of an API request, checked by the AccessChecker.
 */
final class ApiContext
{
    public function __construct(
        public readonly ModuleModel $module,
        public readonly PageModel $page,
        public readonly string $moduleKey,
    ) {
    }
}
