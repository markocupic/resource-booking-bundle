<?php

declare(strict_types=1);

/*
 * This file is part of Contao Isotope Schulfilme Bundle.
 * 
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-isotope-schulfilme-bundle
 */

namespace Markocupic\ContaoIsotopeSchulfilmeBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class Plugin
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * @return array
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create('Markocupic\ContaoIsotopeSchulfilmeBundle\MarkocupicContaoIsotopeSchulfilmeBundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle']),
        ];
    }

    /**
     * @return null|\Symfony\Component\Routing\RouteCollection
     * @throws \Exception
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__ . '/../Resources/config/routes.yml')
            ->load(__DIR__ . '/../Resources/config/routes.yml');
    }
}
