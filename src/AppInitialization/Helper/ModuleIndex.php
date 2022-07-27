<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AppInitialization\Helper;

/**
 * Class ModuleIndex.
 *
 * The module key is necessary to run multiple rbb applications on the same page
 * and is sent as a post parameter on every xhr request.
 *
 * The session data of each rbb instance is stored under $_SESSION[_resource_booking_bundle_attributes][$sessionId.'_'.$userId.'_'.$moduleKey.'_'.$token]
 *
 * The module key (#moduleId_#moduleIndex f.ex. 33_0) contains the module id and the module index
 * The module index is 0, if the current module is the first rbb module on the current page
 * The module index is 1, if the current module is the first rbb module on the current page, etc.
 *
 * Do only run once ModuleIndex::generateModuleIndex() per module instance;
 */
class ModuleIndex
{
    private static int $moduleIndex = -1;

    private static ?int $initTime = null;

    public static function generateModuleIndex(): void
    {
        ++static::$moduleIndex;
    }

    public static function generateInitTime(): void
    {
        static::$initTime = time();
    }

    /**
     * @throws \Exception
     *
     * @return mixed
     */
    public static function getModuleIndex(): int
    {
        if (null === static::$moduleIndex) {
            throw new \Exception('Module index not set. Please use ModuleIndex::generateModuleIndex() first.');
        }

        return static::$moduleIndex;
    }

    public static function getInitTime(): int
    {
        if (null === static::$initTime) {
            throw new \Exception('Init time not set. Please use ModuleIndex::generateInitTime() first.');
        }

        return static::$moduleIndex;
    }
}
