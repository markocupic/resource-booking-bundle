<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MarkocupicResourceBookingExtension extends Extension
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('services.yaml');

        $container->setParameter('markocupic_resource_booking.session.attribute_bag_name', $config['session']['attribute_bag_name']);
        $container->setParameter('markocupic_resource_booking.session.attribute_bag_key', $config['session']['attribute_bag_key']);
        $container->setParameter('markocupic_resource_booking.session.flash_bag_key', $config['session']['flash_bag_key']);
        $container->setParameter('markocupic_resource_booking.cookie.name', $config['cookie']['name']);
        $container->setParameter('markocupic_resource_booking.apps', $config['apps']);
    }

    public function getAlias(): string
    {
        return 'markocupic_resource_booking';
    }
}
