<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 * @package Mitgliederliste RSZ
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Add a palette to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['palettes']['custom_section'] = '{title_legend},name,headline,type;{template_legend:hide},customSectionTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

/**
 * Table tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['customSectionTpl'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_module']['customSectionTpl'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array('tl_mcupic_custom_section', 'getNavigationTemplates'),
    'eval'                    => array('tl_class'=>'w50'),
    'sql'                     => "varchar(64) NOT NULL default ''"
);

  /**
   * Provide miscellaneous methods that are used by the data configuration array.
   *
   * @author Leo Feyer <https://github.com/leofeyer>
   */
class tl_mcupic_custom_section extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * Return all navigation templates as array
     *
     * @return array
     */
    public function getNavigationTemplates()
    {
        return $this->getTemplateGroup('mod_custom_section');
    }

}