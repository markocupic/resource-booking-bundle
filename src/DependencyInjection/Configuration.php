<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\DependencyInjection;

use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('markocupic_resource_booking');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('session')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('attribute_bag_name')
                            ->cannotBeEmpty()
                            ->defaultValue('resource_booking_bundle_attributes')
                        ->end()
                        ->scalarNode('attribute_bag_key')
                            ->cannotBeEmpty()
                            ->cannotBeOverwritten()
                            ->defaultValue('_resource_booking_bundle_attributes')
                        ->end()
                        ->scalarNode('flash_bag_key')
                            ->cannotBeEmpty()
                            ->cannotBeOverwritten()
                            ->defaultValue('_resource_booking_bundle_flash_bag')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cookie')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')
                            ->cannotBeEmpty()
                            ->cannotBeOverwritten()
                            ->defaultValue('_contao_resource_booking_token')
                        ->end()
                    ->end()
                ->end()
                ->append($this->addAppNode())
            ->end()
        ;

        return $treeBuilder;
    }

    private function addAppNode(): NodeDefinition
    {
        return (new TreeBuilder('apps'))
            ->getRootNode()
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->children()
                    ->enumNode('beginnWeek')
                        ->values(RbbConfig::RBB_WEEKDAYS)
                        ->isRequired()
                    ->end()
                    ->integerNode('intBackWeeks')
                        ->isRequired()
                    ->end()
                    ->integerNode('intAheadWeeks')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
