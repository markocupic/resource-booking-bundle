<?php

/**
 * Font Awesome 5 Icon Picker Contao Backend Widget
 * Copyright (c) 2008-2017 Marko Cupic
 * @package fontawesome-icon-picker-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\FontawesomeIconPickerBundle\ContaoBackendWidget;

use Contao\Widget;
use Contao\ContentModel;
use Contao\Input;
use Contao\StringUtil;
use Symfony\Component\Yaml\Yaml;


class Fontawesome5Iconpicker extends Widget
{

    /**
     * @var bool
     */
    protected $blnSubmitInput = true;

    /**
     * @var string
     */
    protected $strTemplate = 'be_widget';

    /**
     * @param mixed $varInput
     * @return mixed
     */
    protected function validator($varInput)
    {
        $varInput = explode('||', $varInput);
        $varInput = serialize($varInput);
        return parent::validator($varInput);
    }

    /**
     * @return string
     */
    public function generate()
    {
        return $this->generatePicker();
    }

    /**
     * @return string
     */
    protected function generatePicker()
    {
        $ContentModel = ContentModel::findByPk(Input::get('id'));

        // Load Font Awesome
        $arrFaIds = $this->getFaIds();

        // Filter
        $html = sprintf('<h3><label>%s</label></h3>', $GLOBALS['TL_LANG']['tl_content']['faIconFilter']);
        $html .= '<input type="text" id="ctrl_faFilter" class="tl_text" placeholder="filter"><br><br>';


        // Build radio-button-list
        $html .= '<div id="iconBox">';

        $inputValue = '';
        $currIconName = null;
        $currIconPrefix = null;
        if (count(StringUtil::deserialize($ContentModel->faIcon, true)) > 0)
        {
            $inputValue = implode('||', StringUtil::deserialize($ContentModel->faIcon, true));
            $arrFa = StringUtil::deserialize($ContentModel->faIcon, true);
            $currIconName = $arrFa[0];
            $currIconPrefix = $arrFa[1];
        }


        $html .= sprintf('<input type="hidden" id="ctrl_faIcon" name="faIcon" value="%s">', $inputValue);
        foreach ($arrFaIds as $arrFa)
        {
            $cssClassChecked = '';
            $iconName = $arrFa['id'];
            $iconLabel = $arrFa['label'];
            $iconUnicode = $arrFa['unicode'];

            if ($currIconName === $iconName)
            {
                $cssClassChecked = ' checked';
            }

            $html .= sprintf('<div onclick="" title="%s" class="font-awesome-icon-item%s">', $iconName, $cssClassChecked);
            $html .= sprintf('<div class="font-id-title">%s</div>', $iconName);
            $html .= sprintf('<i class="fa fa-2x fa-fw %s fa-%s"></i>', $arrFa['faStyle'], $iconName);

            $html .= '<div class="faStyleBox">';

            $selectedStyle = '';
            if (in_array('light', $arrFa['styles']))
            {
                if ($currIconPrefix === 'fal')
                {
                    $selectedStyle = ' selectedStyle';
                }
                $html .= sprintf('<div class="faStyleButton%s" role="button" title="light" data-falabel="%s" data-faiconunicode="%s" data-faiconprefix="fal" data-faiconname="%s">L</div>', $selectedStyle, $iconLabel, $iconUnicode, $iconName);
            }

            $selectedStyle = '';
            if (in_array('regular', $arrFa['styles']))
            {
                if ($currIconPrefix === 'far')
                {
                    $selectedStyle = ' selectedStyle';
                }
                $html .= sprintf('<div class="faStyleButton%s" role="button" title="regular" data-falabel="%s" data-faiconunicode="%s" data-faiconprefix="far" data-faiconname="%s">R</div>', $selectedStyle, $iconLabel, $iconUnicode, $iconName);
            }

            $selectedStyle = '';
            if (in_array('solid', $arrFa['styles']))
            {
                if ($currIconPrefix === 'fas')
                {
                    $selectedStyle = ' selectedStyle';
                }
                $html .= sprintf('<div class="faStyleButton%s" role="button" title="solid" data-falabel="%s" data-faiconunicode="%s" data-faiconprefix="fas" data-faiconname="%s">S</div>', $selectedStyle, $iconLabel, $iconUnicode, $iconName);
            }

            $selectedStyle = '';
            if (in_array('brands', $arrFa['styles']))
            {
                if ($currIconPrefix === 'fab')
                {
                    $selectedStyle = ' selectedStyle';
                }
                $html .= sprintf('<div class="faStyleButton%s" role="button" title="brands" data-falabel="%s" data-faiconunicode="%s" data-faiconprefix="fab" data-faiconname="%s">B</div>', $selectedStyle, $iconLabel, $iconUnicode, $iconName);
            }

            $html .= '</div>';
            $html .= '</div>';

        }

        $html .= '</div>';

        return $html;

    }

    /**
     * Get all FontAwesomeClasses as array from icons.yml
     * Download this file at:
     * https://fontawesome.com/get-started
     * @return array
     */
    protected function getFaIds()
    {
        $arrMatches = [];
        $strFile = file_get_contents(TL_ROOT . '/vendor/markocupic/fontawesome-icon-picker-bundle/src/Resources/fontawesome/icons.yml');

        $arrYaml = Yaml::parse($strFile);
        foreach ($arrYaml as $iconName => $arrItemProps)
        {
            $arrItem = array(
                'id' => $iconName,
                'faClass' => 'fa-' . $iconName,
                'styles' => $arrItemProps['styles'],
                'label' => $arrItemProps['label'],
                'unicode' => $arrItemProps['unicode']
            );

            if (is_array($arrItemProps['styles']))
            {
                if (in_array('regular', $arrItemProps['styles']))
                {
                    $arrItem['style'] = 'solid';
                    $arrItem['faStyle'] = 'far';
                }

                if (in_array('light', $arrItemProps['styles']))
                {
                    $arrItem['style'] = 'light';
                    $arrItem['faStyle'] = 'fal';
                }

                if (in_array('regular', $arrItemProps['styles']))
                {
                    $arrItem['style'] = 'regular';
                    $arrItem['faStyle'] = 'far';
                }

                if (in_array('brands', $arrItemProps['styles']))
                {
                    $arrItem['style'] = 'brands';
                    $arrItem['faStyle'] = 'fab';
                }
            }
            $arrMatches[] = $arrItem;
        }

        return $arrMatches;
    }

}