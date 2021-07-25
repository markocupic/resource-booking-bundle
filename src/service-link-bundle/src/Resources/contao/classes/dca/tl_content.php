<?php

/**
 * @copyright  Marko Cupic 2017 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Service Link Bundle
 * @license    LGPL-3.0+
 * @see        https://github.com/markocupic/service-link-bundle
 *
 */

use Symfony\Component\Yaml\Yaml;


class ce_serviceLink extends Backend
{
    public function __construct()
    {
        parent::__construct();
    }

    public function generatePicker($dc)
    {

        if (Input::post('FORM_SUBMIT'))
        {
            if (Input::post('faIcon') != '')
            {
                $ContentModel = \ContentModel::findByPk($dc->id);
                $ContentModel->faIcon = Input::post('faIcon');
                $ContentModel->save();
            }
        }
        // Load Font Awesome
        $arrFaIds = $this->getFaIds();
        $html = '<fieldset id="ctrl_faIcon" class="tl_radio_container">';
        // Filter
        $html .= '<div class="widget">';
        $html .= '<h3><label>' . $GLOBALS['TL_LANG']['tl_content']['faIconFilter'] . '</label></h3>';
        $html .= '<input type="text" id="faClassFilter" class="tl_text fa-class-filter" placeholder="filter">';
        $html .= '</div>';


        // Build radio-button-list
        $html .= '<div class="widget">';
        $html .= '<h3><label>Icon picker</label></h3>';
        $html .= '<div id="iconBox">';


        $i = 0;
        foreach ($arrFaIds as $arrFa)
        {
            $checked = $cssClassChecked = $cssClassCheckedWithAttribute = '';
            if ($dc->activeRecord->faIcon === 'fa-' . $arrFa['id'])
            {
                $checked = ' checked="checked"';
                $cssClassChecked = ' checked';

                $cssClassCheckedWithAttribute = ' class="checked"';
            }

            $html .= '<div title="' . $arrFa['availableStyles'] . '" class="font-awesome-icon-item' . $cssClassChecked . '" data-faClass="fa-' . $arrFa['id'] . '">';
            $html .= '<input' . $cssClassCheckedWithAttribute . ' id="faIcon_' . $i . '" type="radio" name="faIcon" value="fa-' . $arrFa['id'] . '"' . $checked . '>';
            $html .= '<i class="fa fa-2x fa-fw ' . $arrFa['faStyle'] . ' ' . $arrFa['faClass'] . '"></i>';
            $html .= '<div>' . StringUtil::substr($faClass, 15) . '</div>';
            $html .= '</div>';
            $i++;
        }


        $html .= '</div>';
        $html .= '<p class="tl_help tl_tip" title="">' . $GLOBALS['TL_LANG']['tl_content']['faIcon'][1] . '</p>';
        $html .= '</div>';
        $html .= '</fieldset>';


        // Javascript (Mootools)
        $html .= '
        <script>
            window.addEvent("domready", function(event) {
                if($$("#ctrl_faIcon #iconBox .checked").length){
                    // Scroll to selected icon
                    var myFx = new Fx.Scroll(document.id("iconBox")).toElement($$("#ctrl_faIcon #iconBox .checked")[0]);
                }
                $$("#iconBox input").addEvent("click", function(event){
                    $$("#ctrl_faIcon #iconBox .checked").removeClass("checked");
                    this.getParent("div").addClass("checked");
                });

                // Add event to filter input
                $$("input#faClassFilter").addEvent("input", function(event){
                    var strFilter = this.getProperty("value").trim(" ");
                    var itemCollection = $$(".font-awesome-icon-item");
                    itemCollection.each(function(el){
                        el.setStyle("display","inherit");
                        if(strFilter != "")
                        {
                            if(el.getProperty("data-faClass").contains(strFilter) === false)
                            {
                                el.setStyle("display","none");
                            }
                        }
                    });
                });
            });
        </script>
        ';

        return $html;

    }

    /**
     * Get all FontAwesomeClasses as array from icons.yml
     * Download this file at:
     * https://raw.githubusercontent.com/FortAwesome/Font-Awesome/v4.7.0/src/3.2.1/icons.yml
     * @return array
     */
    protected function getFaIds()
    {

        $strFile = file_get_contents(TL_ROOT . '/vendor/markocupic/service-link-bundle/src/Resources/yml/icons.yml');

        $arrYaml = Yaml::parse($strFile);
        foreach ($arrYaml as $faId => $arrItemProps)
        {
            $arrItem = array(
                'id'              => $faId,
                'style'           => 'solid',
                'faStyle'         => 'fas',
                'faClass'         => 'fa-' . $faId,
                'availableStyles' => implode(' ', $arrItemProps['styles']),

            );

            if (is_array($arrItemProps['styles']))
            {
                if (in_array('regular', $arrItemProps['styles']))
                {
                    $arrItem['style'] = 'regular';
                    $arrItem['faStyle'] = 'far';
                }

                if (in_array('light', $arrItemProps['styles']))
                {
                    $arrItem['style'] = 'light';
                    $arrItem['faStyle'] = 'fal';
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