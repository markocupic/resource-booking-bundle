<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

$GLOBALS['TL_DCA']['tl_office365_member_import'] = [
    // Config
    'config'      => [
        'dataContainer'     => 'Table',
        'enableVersioning'  => true,
        'onsubmit_callback' => [
        ],
        'sql'               => [
            'keys' => [
                'id' => 'primary',
            ]
        ]
    ],
    'edit'        => [
        'buttons_callback' => [
            ['tl_office365_member_import', 'buttonsCallback']
        ]
    ],

    // List
    'list'        => [
        'sorting'           => [
            'fields' => ['tstamp DESC'],
        ],
        'label'             => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ]
        ],
        'operations'        => [
            'edit'   => [
                'href' => 'act=edit',
                'icon' => 'edit.svg'
            ],
            'copy'   => [
                'href' => 'act=copy',
                'icon' => 'copy.svg'
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ],
        ]
    ],

    // Palettes
    'palettes'    => [
        '__selector__' => [],
        'default'      => '{default_legend},title,accountType;{csv_legend},singleSRC',
    ],

    // Subpalettes
    'subpalettes' => [
    ],

    // Fields
    'fields'      => [
        'id'          => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp'      => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'title'       => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'accountType' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'select',
            'options'   => ['student', 'teacher', 'misc'],
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'singleSRC'   => [
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['multiple' => false, 'fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'csv'],
            'sql'       => "blob NULL",
        ],
    ]
];

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_office365_member_import extends Contao\Backend
{

    /**
     * tl_import_from_csv constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if (isset($_POST['importTestMode']))
        {
            $blnTestMode = true;
            $this->initImport($blnTestMode);
        }

        if (isset($_POST['import']))
        {
            $blnTestMode = false;
            $this->initImport($blnTestMode);
        }

        // Display messages from session
        if (!$_POST)
        {
            /** @var \Markocupic\Office365Bundle\Message\SessionMessage $sessionMessage */
            $sessionMessage = \Contao\System::getContainer()->get('Markocupic\Office365Bundle\Message\SessionMessage');

            if ($sessionMessage->hasErrorMessages())
            {
                foreach ($sessionMessage->getErrorMessages() as $msg)
                {
                    \Contao\Message::addError($msg);
                }
            }

            if ($sessionMessage->hasInfoMessages())
            {
                foreach ($sessionMessage->getInfoMessages() as $msg)
                {
                    \Contao\Message::addInfo($msg);
                }
            }
        }
    }

    /**
     * @param bool $blnTestMode
     * @throws Exception
     */
    private function initImport(bool $blnTestMode)
    {
        $accountType = (string) \Contao\Input::post('accountType');
        $objFilesModel = \Contao\FilesModel::findByUuid(\Contao\Input::post('singleSRC'));
        $strDelimiter = ';';
        $strEnclosure = '"';

        // call the import class if file exists
        if (is_file(TL_ROOT . '/' . $objFilesModel->path))
        {
            $objFile = new \Contao\File($objFilesModel->path);
            if (strtolower($objFile->extension) === 'csv')
            {
                $objImport = \Contao\System::getContainer()->get('Markocupic\Office365Bundle\Import\Import');
                $objImport->initImport($accountType, $objFile, $strDelimiter, $strEnclosure, $blnTestMode);
            }
        }
    }

    /**
     * @param $arrButtons
     * @param DC_Table $dc
     * @return mixed
     */
    public function buttonsCallback($arrButtons, DC_Table $dc)
    {
        if (Input::get('act') === 'edit')
        {
            unset($arrButtons['saveNcreate']);
            unset($arrButtons['saveNclose']);
            unset($arrButtons['saveNduplicate']);
            $arrButtons['import'] = '<button type="submit" name="import" id="import" class="tl_submit importButton" accesskey="i">' . $GLOBALS['TL_LANG']['tl_office365_member_import']['importButton'] . '</button>';
            $arrButtons['importTestMode'] = '<button type="submit" name="importTestMode" id="importTestMode" class="tl_submit importTestModeButton" accesskey="t">' . $GLOBALS['TL_LANG']['tl_office365_member_import']['importTestModeButton'] . '</button>';
        }

        // Remove buttons in reportTable view
        if ($this->reportTableMode === true)
        {
            unset($arrButtons['save']);
            unset($arrButtons['saveNclose']);
            unset($arrButtons['saveNcreate']);
            unset($arrButtons['saveNduplicate']);
            unset($arrButtons['import']);
            unset($arrButtons['importTestMode']);
        }

        return $arrButtons;
    }

}
