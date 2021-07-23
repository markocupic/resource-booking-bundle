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

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const KEY = 'markocupic_resource_booking';

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::KEY);

        $treeBuilder->getRootNode()

            ->children()
                ->enumNode('beginnWeek')
                    ->values(RbbConfig::RBB_WEEKDAYS)
                    ->defaultValue('monday')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
