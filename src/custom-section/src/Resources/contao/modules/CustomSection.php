<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Markocupic\CustomSection;

use Patchwork\Utf8;

/**
 * Front end Custom Footer.
 *
 * @author Marko Cupic <m.cupic@gmx.ch>
 */
class CustomSection extends \Module
{

    /**
     * template
     * @var string
     */
    protected $strTemplate = 'mod_custom_section';

    /**
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var \BackendTemplate|object $objTemplate */
            $objTemplate = new \BackendTemplate('be_wildcard');
            if (version_compare(VERSION . '.' . BUILD, '4.0.0', '<'))
            {
                $objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['custom_section'][0]) . ' ###';
            }else{
                $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['custom_section'][0]) . ' ###';
            }
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        // Overwrite default template
        if($this->customSectionTpl != '')
        {
            $this->strTemplate = $this->customSectionTpl;
        }

        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        //
    }

}
