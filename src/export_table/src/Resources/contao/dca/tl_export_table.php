<?php

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

$GLOBALS['TL_DCA']['tl_export_table'] = [
    // Config
    'config'      => [
        'dataContainer' => 'Table',
        'sql'           => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    // Buttons callback
    'edit'        => [
        'buttons_callback' => [['tl_export_table', 'buttonsCallback']],
    ],

    // List
    'list'        => [
        'sorting'           => [
            'fields' => ['tstamp DESC'],
        ],
        'label'             => [
            'fields' => ['title', 'export_table'],
            'format' => '%s Tabelle: %s',
        ],
        'global_operations' => [],
        'operations'        => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ],
            'show'   => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.gif',
            ],
        ],
    ],
    // Palettes
    'palettes'    => [
        '__selector__' => ['activateDeepLinkExport'],
        'default'      => '{title_legend},title;{settings},export_table,exportType,fields,filterExpression,sortBy,sortByDirection,arrayDelimiter;{deep_link_legend},activateDeepLinkExport',
    ],
    'subpalettes' => [
        'activateDeepLinkExport' => 'deepLinkExportKey,deepLinkInfo',
    ],
    // Fields
    'fields'      => [

        'id'                     => [
            'label'  => ['ID'],
            'search' => true,
            'sql'    => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp'                 => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'                  => [
            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'export_table'           => [
            'label'            => &$GLOBALS['TL_LANG']['tl_export_table']['export_table'],
            'inputType'        => 'select',
            'options_callback' => [
                'tl_export_table',
                'optionsCbGetTables',
            ],
            'eval'             => [
                'multiple'           => false,
                'mandatory'          => true,
                'includeBlankOption' => true,
                'submitOnChange'     => true,
            ],
            'sql'              => "varchar(255) NOT NULL default ''",
        ],
        'filterExpression'       => [
            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['filterExpression'],
            'inputType' => 'text',
            'eval'      => [
                'mandatory'      => false,
                'preserveTags'   => false,
                'allowHtml'      => true,
                'decodeEntities' => false,
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'fields'                 => [
            'label'            => &$GLOBALS['TL_LANG']['tl_export_table']['fields'],
            'inputType'        => 'checkboxWizard',
            'options_callback' => [
                'tl_export_table',
                'optionsCbSelectedFields',
            ],
            'eval'             => [
                'multiple'   => true,
                'mandatory'  => true,
                'orderField' => 'orderFields',
            ],
            'sql'              => "blob NULL",
        ],
        'arrayDelimiter'         => [
            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['arrayDelimiter'],
            'exclude'   => true,
            'search'    => true,
            'default'   => '||',
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default '||'",
        ],
        'orderFields'            => [
            'label' => &$GLOBALS['TL_LANG']['tl_export_table']['orderFields'],
            'sql'   => "blob NULL",
        ],
        'sortBy'                 => [
            'label'            => &$GLOBALS['TL_LANG']['tl_export_table']['sortBy'],
            'inputType'        => 'select',
            'options_callback' => [
                'tl_export_table',
                'optionsCbSelectedFields',
            ],
            'eval'             => [
                'multiple'  => false,
                'mandatory' => false,
            ],
            'sql'              => "blob NULL",
        ],
        'sortByDirection'        => [
            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['sortByDirection'],
            'inputType' => 'select',
            'options'   => ['ASC', 'DESC'],
            'eval'      => [
                'multiple'  => false,
                'mandatory' => false,
            ],
            'sql'       => "blob NULL",
        ],
        'exportType'             => [
            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['exportType'],
            'inputType' => 'select',
            'options'   => ['csv', 'xml'],
            'eval'      => [
                'multiple'  => false,
                'mandatory' => false,
            ],
            'sql'       => "blob NULL",
        ],
        'activateDeepLinkExport' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['activateDeepLinkExport'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'deepLinkExportKey'      => [

            'label'     => &$GLOBALS['TL_LANG']['tl_export_table']['deepLinkExportKey'],
            'exclude'   => true,
            'search'    => true,
            'default'   => md5(microtime() . rand()),
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 200],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'deepLinkInfo'           => [
            'input_field_callback' => ['tl_export_table', 'generateDeepLinkInfo'],
            'eval'                 => ['doNotShow' => true],
        ],
    ],
];

/**
 * Class tl_export_table
 * Provide miscellaneous methods that are used by the data configuration array.
 * Copyright : &copy; 2014 Marko Cupic
 * @author Marko Cupic 2014
 * @package export_table
 */
class tl_export_table extends Backend
{

    public function __construct()
    {
        parent::__construct();
        if (isset($_POST['exportTable']) && $_POST['FORM_SUBMIT'] === 'tl_export_table')
        {
            unset($_POST['exportTable']);
            $objDb = \Contao\Database::getInstance()->prepare('SELECT * FROM tl_export_table WHERE id=?')->execute(Input::get('id'));
            if ($objDb->numRows)
            {
                $export = \Contao\System::getContainer()->get('Markocupic\ExportTable\Export\ExportTable');
                $export->prepareExport();
                exit();
            }
        }
    }

    /**
     * option_callback
     * @return array
     */
    public function optionsCbGetTables()
    {
        $objTables = \Contao\Database::getInstance()->listTables();
        $arrOptions = [];
        foreach ($objTables as $table)
        {
            $arrOptions[] = $table;
        }
        return $arrOptions;
    }

    /**
     * buttons_callback
     * @param $arrButtons
     * @param DC_Table $dc
     * @return mixed
     */
    public function buttonsCallback($arrButtons, DC_Table $dc)
    {
        if (\Contao\Input::get('act') == 'edit')
        {
            $save = $arrButtons['save'];
            $exportTable = '<button type="submit" name="exportTable" id="exportTable" class="tl_submit" accesskey="n">' . $GLOBALS['TL_LANG']['tl_export_table']['launchExportButton'] . '</button>';
            $saveNclose = $arrButtons['saveNclose'];

            unset($arrButtons);

            // Set correct order
            $arrButtons = [
                'save'        => $save,
                'exportTable' => $exportTable,
                'saveNclose'  => $saveNclose,
            ];
        }

        return $arrButtons;
    }

    /**
     * option_callback
     * @return array
     */
    public function optionsCbSelectedFields()
    {
        $objDb = \Contao\Database::getInstance()->prepare("SELECT * FROM tl_export_table WHERE id = ? LIMIT 0,1")->execute(\Contao\Input::get('id'));
        if ($objDb->export_table == '')
        {
            return;
        }
        $objFields = \Contao\Database::getInstance()->listFields($objDb->export_table, 1);
        $arrOptions = [];
        foreach ($objFields as $field)
        {
            if (in_array($field['name'], $arrOptions))
            {
                continue;
            }
            if ($field['name'] === 'PRIMARY')
            {
                continue;
            }
            $arrOptions[$field['name']] = $field['name'] . ' [' . $field['type'] . ']';
        }
        return $arrOptions;
    }

    /**
     * Input-field-callback
     * @return string
     */
    public function generateDeepLinkInfo()
    {
        $objDb = \Contao\Database::getInstance()->prepare('SELECT * FROM tl_export_table WHERE id=? LIMIT 0,1')->execute(\Contao\Input::get('id'));
        $key = $objDb->deepLinkExportKey;
        $href = sprintf('%s/_export_table_download_table?action=exportTable&amp;key=%s', \Contao\Environment::get('url'), $key);

        $html = '
<div class="clr widget deep_link_info">
<br /><br />
<table cellpadding="0" cellspacing="0" width="100%" summary="">
	<tr class="odd">
		<td><h2>' . $GLOBALS['TL_LANG']['tl_export_table']['deepLinkInfoText'] . '</h2></td>
    </tr>
	<tr class="even">
		<td><a href="' . $href . '">' . $href . '</a></td>
	</tr>
</table>
</div>
				';

        return $html;
    }
}
