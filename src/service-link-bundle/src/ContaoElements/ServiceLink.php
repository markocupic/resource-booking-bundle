<?php

/**
 * @copyright  Marko Cupic 2017 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Service Link Bundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/markocupic/service-link-bundle
 *
 */

namespace Markocupic\ServiceLinkBundle\ContaoElements;

/**
 * Class ServiceLink
 * @package Markocupic
 */
class ServiceLink extends \ContentElement
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'ce_servicelink';

    /**
     * @return string
     */
    public function generate()
    {
        if(TL_MODE === 'BE')
        {

            $this->strTemplate = 'be_servicelink';

            /** @var \BackendTemplate|object $objTemplate */
            $this->Template = new \BackendTemplate($this->strTemplate);
            $arrFa = deserialize($this->faIcon,true);
            $this->Template->faIconName = $arrFa[0];
            $this->Template->faIconPrefix = $arrFa[1];
            $this->Template->faIconUnicode = $arrFa[2];
            $this->Template->iconClass = $this->iconClass;
            $this->Template->headline = $this->headline;
            $this->Template->serviceLinkText = $this->serviceLinkText;
            $this->Template->buttonClass = $this->buttonClass;
            $this->Template->buttonText = $this->buttonText;
        }

        return parent::generate();

    }


    /**
     * Generate the content element
     */
    protected function compile()
    {
        global $objPage;

        // Clean the RTE output
        if ($objPage->outputFormat == 'xhtml')
        {
            $this->text = \StringUtil::toXhtml($this->text);
        }
        else
        {
            $this->text = \StringUtil::toHtml5($this->text);
        }

        $arrFa = deserialize($this->faIcon,true);

        $this->Template->faIconName = $arrFa[0];
        $this->Template->faIconPrefix = $arrFa[1];
        $this->Template->faIconUnicode = $arrFa[2];

        $this->Template->serviceLinkText = \StringUtil::encodeEmail($this->serviceLinkText);
        $this->Template->buttonJumpTo = $this->buttonJumpTo;

    }
}
