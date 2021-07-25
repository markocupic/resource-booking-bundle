<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Table tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['palettes']['bootstrapYoutubeResponsiveEmbed'] = '{type_legend},type,headline;{source_legend},playerType,movieId;{poster_legend:hide},posterSRC;{player_legend},playerAspectRatio,caption,autoplay;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space;{invisible_legend:hide},invisible,start,stop';
$GLOBALS['TL_DCA']['tl_content']['fields']['playerAspectRatio'] = array
(
    'label' => &$GLOBALS['TL_LANG']['tl_content']['playerAspectRatio'],
    'default' => 'text',
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options' => array('embed-responsive-16by9', 'embed-responsive-4by3'),
    'reference' => &$GLOBALS['TL_LANG']['tl_content'],
    'eval' => array('helpwizard' => false, 'chosen' => true),
    'sql' => "varchar(32) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_content']['fields']['playerType'] = array
(
    'label' => &$GLOBALS['TL_LANG']['tl_content']['playerType'],
    'default' => 'text',
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'options' => array('youtube', 'vimeo', 'dropbox'),
    'eval' => array('helpwizard' => false, 'chosen' => true),
    'sql' => "varchar(32) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_content']['fields']['movieId'] = array
(
    'label' => &$GLOBALS['TL_LANG']['tl_content']['movieId'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => array('rgxp' => 'url', 'mandatory' => true),
    'sql' => "varchar(512) NOT NULL default ''"
);

