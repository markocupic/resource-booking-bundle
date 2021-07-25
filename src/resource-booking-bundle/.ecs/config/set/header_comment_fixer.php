<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;

return static function (ContainerConfigurator $containerConfigurator): void {

    $services = $containerConfigurator->services();

    $services
        ->set(HeaderCommentFixer::class)
        ->call('configure', [[
            'header' => "This file is part of Resource Booking Bundle.\n\n(c) Marko Cupic ".date("Y")." <m.cupic@gmx.ch>\n@license MIT\n@link https://github.com/markocupic/resource-booking-bundle",
        ]])
    ;
};