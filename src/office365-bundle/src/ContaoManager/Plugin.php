<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

namespace Markocupic\Office365Bundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * Class Plugin
 * @package Markocupic\Office365Bundle\ContaoManager
 */
class Plugin implements BundlePluginInterface, ConfigPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create('Markocupic\Office365Bundle\MarkocupicOffice365Bundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle']),
        ];
    }

    /**
     * @param LoaderInterface $loader
     * @param array $managerConfig
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig)
    {
        $loader->load(__DIR__.'/../Resources/config/parameters.yml');
        $loader->load(__DIR__.'/../Resources/config/services.yml');
        $loader->load(__DIR__.'/../Resources/config/listener.yml');
    }
}

