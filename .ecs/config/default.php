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

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ECSConfig): void {
    // Contao
    $ECSConfig->import(__DIR__.'../../../../../contao/easy-coding-standard/config/contao.php');

    // Custom
    $ECSConfig->import(__DIR__.'/set/config.php');
};

