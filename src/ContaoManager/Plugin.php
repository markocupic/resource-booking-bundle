<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Markocupic\ResourceBookingBundle\DependencyInjection\Compiler\AddSessionBagsPass;

/**
 * Class Plugin
 * @package Markocupic\ResourceBookingBundle\ContaoManager
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface, ConfigPluginInterface
{
    /**
     * @param LoaderInterface $loader
     * @param array $managerConfig
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig)
    {
        $loader->load(__DIR__ . '/../Resources/config/parameters.yml');
        $loader->load(__DIR__ . '/../Resources/config/listener.yml');
        $loader->load(__DIR__ . '/../Resources/config/cron.yml');
        $loader->load(__DIR__ . '/../Resources/config/migration.yml');
        $loader->load(__DIR__ . '/../Resources/config/services.yml');

        // Register session bag
        $loader->load(static function (ContainerBuilder $container) {
            $container->addCompilerPass(new AddSessionBagsPass());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create('Markocupic\ResourceBookingBundle\MarkocupicResourceBookingBundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle'])
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__ . '/../Resources/config/routing.yml')
            ->load(__DIR__ . '/../Resources/config/routing.yml');
    }

}

