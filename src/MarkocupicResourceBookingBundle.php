<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MarkocupicResourceBookingBundle
 * @package Markocupic\ResourceBookingBundle
 */
class MarkocupicResourceBookingBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}

