<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class AddAjaxControllerPass implements CompilerPassInterface
{
    /**
     * @throws ServiceNotFoundException
     */
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->findDefinition('Markocupic\ResourceBookingBundle\EventSubscriber\AjaxRequestEventSubscriber');

        // find all service IDs with the huh.api.resource tag
        $taggedServices = $container->findTaggedServiceIds('markocupic_resource_booking.ajax_controller');

        foreach ($taggedServices as $id => $tags) {
            // a service could have the same tag twice
            foreach ($tags as $attributes) {
                if (!isset($attributes['alias'])) {
                    throw new InvalidArgumentException(sprintf('Missing tag information "alias" on markocupic_resource_booking.ajax_controller tagged service "%s".', $id));
                }

                $definition->addMethodCall(
                    'add',
                    [
                        new Reference($id),
                        $attributes['alias'],
                        $id,
                    ]
                );
            }
        }
    }
}
