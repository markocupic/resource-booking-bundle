<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AddSessionBagsPass
 * @package Markocupic\ResourceBookingBundle\DependencyInjection\Compiler
 */
class AddSessionBagsPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('session'))
        {
            return;
        }
        $session = $container->findDefinition('session');
        $session->addMethodCall('registerBag', [new Reference('Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag')]);
    }
}
