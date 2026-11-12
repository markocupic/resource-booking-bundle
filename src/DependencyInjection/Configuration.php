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
                ->booleanNode('purge_past_bookings_with_cron')
                    ->defaultFalse()
                ->end()
                ->append($this->addAppNode())
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * App configurations: several configurations can be defined, each frontend
     * module selects one of them (tl_module.resourceBooking_appConfig).
     *
     * The number of weeks back/ahead and the auto confirmation are set per
     * resource since version 6 (tl_resource_booking_resource.weeksBack/weeksAhead/autoConfirm).
     * The former keys are deprecated and only read by the migration.
     */
    private function addAppNode(): NodeDefinition
    {
        $deprecationMessage = 'The "%node%" option at "%path%" is deprecated.';

        return (new TreeBuilder('apps'))
            ->getRootNode()
            ->info('App configurations, which can be selected in the frontend module settings.')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->beforeNormalization()
                    ->ifArray()
                    ->then(
                        static function (array $app): array {
                            // Map the former keys (before version 6) to the new keys
                            if (isset($app['beginnWeek']) && !isset($app['begin_week'])) {
                                $app['begin_week'] = $app['beginnWeek'];
                            }

                            if (isset($app['permittedUploadFields']) && !isset($app['permitted_form_fields'])) {
                                $app['permitted_form_fields'] = $app['permittedUploadFields'];
                            }

                            return $app;
                        }
                    )
                ->end()
                ->children()
                    ->enumNode('begin_week')
                        ->info('The weekday on which the week calendar starts.')
                        ->values(RbbConfig::RBB_WEEKDAYS)
                        ->defaultValue('monday')
                    ->end()
                    ->arrayNode('permitted_form_fields')
                        ->info('Custom form fields of the booking form have to be registered here. bookingDescription, itemsBooked and bookingRepeatStopWeekTstamp are always permitted.')
                        ->scalarPrototype()->end()
                        ->defaultValue([])
                    ->end()
                    // Deprecated since version 6: use "begin_week" instead
                    ->enumNode('beginnWeek')
                        ->values(RbbConfig::RBB_WEEKDAYS)
                        ->setDeprecated('markocupic/resource-booking-bundle', '6.0', $deprecationMessage.' Use "begin_week" instead.')
                    ->end()
                    // Deprecated since version 6: use "permitted_form_fields" instead
                    ->arrayNode('permittedUploadFields')
                        ->scalarPrototype()->end()
                        ->setDeprecated('markocupic/resource-booking-bundle', '6.0', $deprecationMessage.' Use "permitted_form_fields" instead.')
                    ->end()
                    // Deprecated since version 6: set per resource, only read by the migration
                    ->integerNode('intBackWeeks')
                        ->setDeprecated('markocupic/resource-booking-bundle', '6.0', $deprecationMessage.' Set "Weeks back" in the resource settings instead.')
                    ->end()
                    ->integerNode('intAheadWeeks')
                        ->setDeprecated('markocupic/resource-booking-bundle', '6.0', $deprecationMessage.' Set "Weeks ahead" in the resource settings instead.')
                    ->end()
                    ->booleanNode('autoConfirm')
                        ->setDeprecated('markocupic/resource-booking-bundle', '6.0', $deprecationMessage.' Set "Confirm bookings automatically" in the resource settings instead.')
                    ->end()
                ->end()
            ->end()
        ;
    }
}
