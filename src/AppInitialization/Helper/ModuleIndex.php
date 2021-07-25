<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AppInitialization\Helper;

/**
 * Class ModuleIndex.
 *
 * The module key is necessary to run multiple rbb applications on the same page
 * and is sent as a post parameter in every xhr request.
 *
 * The session data of each rbb instance is stored under $_SESSION[_resource_booking_bundle_attributes][#moduleKey#]
 *
 * The module key (#moduleId_#moduleIndex f.ex. 33_0) contains the module id and the module index
 * The module index is 0, if the current module is the first rbb module on the current page
 * The module index is 1, if the current module is the first rbb module on the current page, etc.
 *
 * Do only run once ModuleIndex::setModuleIndex() per module instance;
 */
class ModuleIndex
{
    /**
     * @var int
     */
    private static $moduleIndex;

    public static function setModuleIndex(): void
    {
        if (null === static::$moduleIndex) {
            static::$moduleIndex = 0;
        } else {
            ++static::$moduleIndex;
        }
    }

    /**
     * @throws \Exception
     *
     * @return mixed
     */
    public static function getModuleIndex(): int
    {
        if (null === static::$moduleIndex) {
            throw new \Exception('Module index not set. Please use ModuleIndex::setModuleIndex() first.');
        }

        return static::$moduleIndex;
    }
}
