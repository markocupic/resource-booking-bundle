<?php
/**
 * @copyright  Marko Cupic 2017 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Service Link Bundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/markocupic/service-link-bundle
 *
 */
namespace Markocupic\ServiceLinkBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

/**
 * Plugin for the Contao Manager.
 *
 * @author Marko Cupic
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create('Markocupic\ServiceLinkBundle\MarkocupicServiceLinkBundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle','Markocupic\FontawesomeIconPickerBundle\MarkocupicFontawesomeIconPickerBundle']) 
                ->setReplace(['service_link']),
        ];
    }
}
