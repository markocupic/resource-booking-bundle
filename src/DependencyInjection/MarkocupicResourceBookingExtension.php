<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MarkocupicResourceBookingExtension.
 */
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
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yml');

        $container->setParameter('markocupic_resource_booking.session.attribute_bag_name', $config['session']['attribute_bag_name']);
        $container->setParameter('markocupic_resource_booking.session.attribute_bag_key', $config['session']['attribute_bag_key']);
        $container->setParameter('markocupic_resource_booking.session.flash_bag_key', $config['session']['flash_bag_key']);
        $container->setParameter('markocupic_resource_booking.cookie.name', $config['cookie']['name']);
        $container->setParameter('markocupic_resource_booking.apps', $config['apps']);
    }

    public function getAlias()
    {
        return 'markocupic_resource_booking';
    }
}