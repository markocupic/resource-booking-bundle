<?php

/**
 * This file is part of a markocupic Contao Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Contao Schule Ettiswil Licenses
 * @license    GPL-3.0-or-later
 * @see        https://github.com/markocupic/contao-schule-ettiswil-licenses-bundle
 *
 */

declare(strict_types=1);

namespace Markocupic\ContaoSchuleEttiswilLicensesBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

/**
 * Class Plugin
 *
 * @package Markocupic\ContaoSchuleEttiswilLicensesBundle\ContaoManager
 */
class Plugin implements BundlePluginInterface
{
    /**
     * @param ParserInterface $parser
     * @return array
     */
    public function getBundles(ParserInterface $parser)
    {

        return [
            BundleConfig::create('Markocupic\ContaoSchuleEttiswilLicensesBundle\MarkocupicContaoSchuleEttiswilLicensesBundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle']),
        ];
    }

}

