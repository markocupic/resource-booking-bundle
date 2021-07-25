<?php
/**
 * Font Awesome 5 Icon Picker Contao Backend Widget
 * Copyright (c) 2008-2017 Marko Cupic
 * @package fontawesome-icon-picker-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

// Palettes
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace('{files_legend', '{fontawesome_icon_picker_legend:hide},fontawesomIconPickerFontawesomeSRC;{files_legend', $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']);


// Fields
$GLOBALS['TL_DCA']['tl_settings']['fields']['fontawesomIconPickerFontawesomeSRC'] = array
(
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['fontawesomIconPickerFontawesomeSRC'],
    'inputType' => 'text',
    'eval' => array('mandatory' => false, 'tl_class' => 'w50')
);