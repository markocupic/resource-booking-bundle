<?php
/**
 * Font Awesome 5 Icon Picker Contao Backend Widget
 * Copyright (c) 2008-2017 Marko Cupic
 * @package fontawesome-icon-picker-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


/**
 * Add fields to tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['faIcon'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_content']['faIcon'],
    'search' => true,
    'inputType' => 'fontawesome5Iconpicker',
    'eval' => array('doNotShow' => true),
    'sql' => "blob NULL",
);



