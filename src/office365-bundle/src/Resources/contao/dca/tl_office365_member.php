<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

$GLOBALS['TL_DCA']['tl_office365_member'] = [
    // Config
    'config'      => [
        'dataContainer'     => 'Table',
        'enableVersioning'  => true,
        'onsubmit_callback' => [
            ['tl_office365_member', 'storeDateAdded']
        ],
        'onload_callback'   => [
            ['tl_office365_member', 'checkSendingEmailsIsPermittedInContaoBackendSettings']
        ],
        'sql'               => [
            'keys' => [
                'id'    => 'primary',
                //'username' => 'unique',
                'email' => 'index'
            ]
        ]
    ],

    // List
    'list'        => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['name'],
            'panelLayout' => 'filter;sort,search,limit'
        ],
        'label'             => [
            'fields'      => ['name', 'email', 'initialPassword', 'accountType', 'teacherAcronym'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ]
        ],
        'operations'        => [
            'edit'      => [
                'href' => 'act=edit',
                'icon' => 'edit.svg'
            ],
            'copy'      => [
                'href' => 'act=copy',
                'icon' => 'copy.svg'
            ],
            'delete'    => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ],
            'toggle'    => [
                'icon'            => 'visible.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => ['tl_office365_member', 'toggleIcon']
            ],
            'emailSent' => [
                'label'                => &$GLOBALS['TL_LANG']['tl_office365_member']['emailSent'],
                'attributes'           => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field'                     => 'passwordEmailSent',
                    'check_permission_callback' => ['tl_office365_member', 'shouldSendEmail'],
                    'options'                   => [
                        [
                            'value' => '',
                            'icon'  => 'bundles/markocupicoffice365/uncheck.png'
                        ],
                        [
                            'value' => '1',
                            'icon'  => 'bundles/markocupicoffice365/check.png'
                        ]
                    ]
                ]
            ]
        ]
    ],

    // Palettes
    'palettes'    => [
        '__selector__' => [],
        'default'      => '{personal_legend},name,firstname,lastname,studentId,ahv,accountType,teacherAcronym,enteredIn,dateAdded;{contact_legend},email;{notice_legend},notice;{login_legend},username,initialPassword',
    ],

    // Subpalettes
    'subpalettes' => [
    ],

    // Fields
    'fields'      => [
        'id'                => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp'            => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'studentId'         => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'rgxp' => 'natural', 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0"
        ],
        'ahv'               => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'rgxp' => 'natural', 'maxlength' => 20, 'tl_class' => 'w50'],
            'sql'       => "bigint(20) unsigned NOT NULL default 0"
        ],
        'firstname'         => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'lastname'          => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'name'              => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'email'             => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'rgxp' => 'email', 'unique' => true, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'accountType'       => [
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'inputType' => 'select',
            'options'   => ['student', 'teacher', 'misc'],
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'teacherAcronym'    => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'username'          => [
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'unique' => true, 'rgxp' => 'extnd', 'nospace' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => 'varchar(64) BINARY NULL'
        ],
        'initialPassword'   => [
            'exclude'   => true,
            'sorting'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'nospace' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'enteredIn'         => [
            'exclude'   => true,
            'sorting'   => true,
            'flag'      => 6,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(11) NOT NULL default ''"
        ],
        'disable'           => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''"
        ],
        'dateAdded'         => [
            'label'   => &$GLOBALS['TL_LANG']['MSC']['dateAdded'],
            'default' => time(),
            'sorting' => true,
            'flag'    => 6,
            'eval'    => ['rgxp' => 'datim', 'doNotCopy' => true],
            'sql'     => "int(10) unsigned NOT NULL default 0"
        ],
        'notice'            => [
            'exclude'   => true,
            'sorting'   => true,
            'search'    => 'true',
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'maxlength' => 200, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],
        'passwordEmailSent' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''"
        ],

    ]
];

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_office365_member extends Contao\Backend
{
    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');
    }

    public function checkSendingEmailsIsPermittedInContaoBackendSettings()
    {
        // Activate sending emails in the contao settings
        if (!strlen(\Contao\Input::get('act')) && \Contao\Input::get('do') === 'office365_member' && !\Contao\Config::get('allowSendingEmailInTheOffice365BackendModule'))
        {
            \Contao\Message::addInfo('Sending Message is not possible! You have to enable it in the contao backend settings under "office365 settings".');
        }
    }

    public function shouldSendEmail($table, $hasteAjaxOperationSettings, &$hasPermission)
    {
        if (\Contao\Config::get('allowSendingEmailInTheOffice365BackendModule'))
        {
            $hasPermission = true;

            $objMember = \Markocupic\Office365Bundle\Model\Office365MemberModel::findByPk(\Contao\Input::post('id'));

            if ($objMember !== null && !$objMember->emailSent && \Contao\Input::post('action') === 'hasteAjaxOperation' && \Contao\Input::post('operation') === 'emailSent')
            {
                $objEmail = \Contao\System::getContainer()->get(Markocupic\Office365Bundle\Email\SendPassword::class);
                $objEmail->sendCredentials($objMember);
            }
        }
        else
        {
            $hasPermission = false;
        }
    }

    /**
     * Return the "toggle visibility" button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (Contao\Input::get('tid'))
        {
            $this->toggleVisibility(Contao\Input::get('tid'), (Contao\Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->hasAccess('tl_office365_member::disable', 'alexf'))
        {
            return '';
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . $row['disable'];

        if ($row['disable'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . Contao\StringUtil::specialchars($title) . '"' . $attributes . '>' . Contao\Image::getHtml($icon, $label, 'data-state="' . ($row['disable'] ? 0 : 1) . '"') . '</a> ';
    }

    /**
     * Disable/enable a user group
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param Contao\DataContainer $dc
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, Contao\DataContainer $dc = null)
    {
        // Set the ID and action
        Contao\Input::setGet('id', $intId);
        Contao\Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }

        // Trigger the onload_callback
        if (is_array($GLOBALS['TL_DCA']['tl_office365_member']['config']['onload_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_office365_member']['config']['onload_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        // Check the field access
        if (!$this->User->hasAccess('tl_office365_member::disable', 'alexf'))
        {
            throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to activate/deactivate member ID ' . $intId . '.');
        }

        // Set the current record
        if ($dc)
        {
            $objRow = $this->Database->prepare("SELECT * FROM tl_office365_member WHERE id=?")
                ->limit(1)
                ->execute($intId);

            if ($objRow->numRows)
            {
                $dc->activeRecord = $objRow;
            }
        }

        $objVersions = new Contao\Versions('tl_office365_member', $intId);
        $objVersions->initialize();

        // Reverse the logic (members have disabled=1)
        $blnVisible = !$blnVisible;

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_office365_member']['fields']['disable']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_office365_member']['fields']['disable']['save_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, $dc);
                }
                elseif (is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, $dc);
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_office365_member SET tstamp=$time, disable='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        if ($dc)
        {
            $dc->activeRecord->tstamp = $time;
            $dc->activeRecord->disable = ($blnVisible ? '1' : '');
        }

        // Trigger the onsubmit_callback
        if (is_array($GLOBALS['TL_DCA']['tl_office365_member']['config']['onsubmit_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_office365_member']['config']['onsubmit_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        $objVersions->create();
    }

    /**
     * @param $dc
     */
    public function storeDateAdded($dc)
    {
        // Front end call
        if (!$dc instanceof Contao\DataContainer)
        {
            return;
        }

        // Return if there is no active record (override all)
        if (!$dc->activeRecord || $dc->activeRecord->dateAdded > 0)
        {
            return;
        }

        // Fallback solution for existing accounts
        if ($dc->activeRecord->enteredIn > 0)
        {
            $time = $dc->activeRecord->enteredIn;
        }
        else
        {
            $time = time();
        }

        $this->Database->prepare("UPDATE tl_office365_member SET dateAdded=? WHERE id=?")
            ->execute($time, $dc->id);
    }

}
