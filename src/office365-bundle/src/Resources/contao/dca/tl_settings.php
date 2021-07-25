<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

// Legend


// Fields
$GLOBALS['TL_DCA']['tl_settings']['fields']['allowSendingEmailInTheOffice365BackendModule'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['allowSendingEmailInTheOffice365BackendModule'],
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr']
];

// Extend the default palettes
Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addLegend('office365_legend', 'default', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->addField(['allowSendingEmailInTheOffice365BackendModule'], 'office365_legend', Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings');


