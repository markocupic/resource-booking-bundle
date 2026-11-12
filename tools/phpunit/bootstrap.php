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

/*
 * The tests can be run in two ways:
 *
 * 1. Standalone (CI): "composer install" in the bundle directory
 *    -> <bundle>/vendor/autoload.php
 * 2. Inside a Contao installation (e.g. in PhpStorm), where the bundle
 *    lives in vendor/markocupic/resource-booking-bundle
 *    -> <project>/vendor/autoload.php
 */
$autoloadFiles = [
    __DIR__.'/../../vendor/autoload.php',
    __DIR__.'/../../../../autoload.php',
];

$loader = null;

foreach ($autoloadFiles as $autoloadFile) {
    if (is_file($autoloadFile)) {
        $loader = require $autoloadFile;
        break;
    }
}

if (null === $loader) {
    throw new RuntimeException('Could not find the Composer autoloader. Run "composer install" in the bundle or in the Contao installation.');
}

// Register the bundle and its tests explicitly: the "autoload-dev" section of a
// dependency is not loaded by the project's autoloader, and the bundle may be
// located in vendor/ without being installed via Composer.
$loader->addPsr4('Markocupic\\ResourceBookingBundle\\', __DIR__.'/../../src');
$loader->addPsr4('Markocupic\\ResourceBookingBundle\\Tests\\', __DIR__.'/../../tests');
