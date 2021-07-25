<?php

/*
 * This file is part of markocupic/contao-schule-ettiswil-licenses-bundle.
 *
 * (c) Marko Cupic
 *
 * @license MIT
 */

use Contao\Backend;
use Contao\Input;
use Contao\System;
use Markocupic\ContaoSchuleEttiswilLicensesBundle\ExportData\Excel;

/**
 * Table tl_schule_ettiswil_licenses
 */
$GLOBALS['TL_DCA']['tl_schule_ettiswil_licenses'] = [
    // Config
    'config'      => [
        'dataContainer'    => 'Table',
        'enableVersioning' => true,
        'sql'              => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
        'onload_callback'  => [
            ['tl_schule_ettiswil_licenses', 'route'],
        ],
    ],
    'list'        => [
        'sorting'           => [
            'mode'        => 2,
            'fields'      => ['title', 'userid', 'passphrase', 'expirationdate', 'department', 'topic'],
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label'             => [
            'fields' => ['title'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all'         => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
            'excelExport' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses']['excelExport'],
                'href'       => 'action=excelExport',
                'class'      => 'header_icon header_excel_export',
                'icon'       => 'bundles/markocupiccontaoschuleettiswillicenses/excel.svg',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="i"',
            ],
        ],
        'operations'        => [
            'edit'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ],
            'copy'   => [
                'label' => &$GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ],
            'delete' => [
                'label'      => &$GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
            'show'   => [
                'label'      => &$GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses']['show'],
                'href'       => 'act=show',
                'icon'       => 'show.gif',
                'attributes' => 'style="margin-right:3px"',
            ],
        ],
    ],
    // Palettes
    'palettes'    => [
        '__selector__' => ['addSubpalette'],
        'default'      => '{title_legend},title,userid,passphrase,notice;{expirationDate_legend},expirationdate;{config_legend},department,topic',
    ],
    // Subpalettes
    'subpalettes' => [
        'addSubpalette' => 'textareaField',
    ],
    // Fields
    'fields'      => [
        'id'             => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp'         => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'          => [
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'userid'         => [
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'passphrase'     => [
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'notice'         => [
            'inputType' => 'textarea',
            'exclude'   => true,
            'search'    => true,
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => 'text NOT NULL',
        ],
        'department'     => [
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => $GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses'],
            'options'   => ['kg', 'ps', 'iss', 'if', 'all'],
            'eval'      => ['mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'topic'          => [
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => $GLOBALS['TL_LANG']['tl_schule_ettiswil_licenses'],
            'options'   => ['mt', 'de', 'en', 'fr', 'rzg', 'lk', 'pu', 'bus', 'nat', 'mu', 'mui', 'bg', 'ttg', 'misc'],
            'eval'      => ['mandatory' => true, 'includeBlankOption' => false, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'expirationdate' => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
            'sql'       => "varchar(11) NOT NULL default ''",
        ],
    ],
];

/**
 * Class tl_schule_ettiswil_licenses
 */
class tl_schule_ettiswil_licenses extends Backend
{
    /**
     * tl_schule_ettiswil_licenses constructor.
     */
    public function route()
    {
        if (Input::get('action') == 'excelExport') {
            $objExport = System::getContainer()
                ->get(Excel::class);

            $objExport->excelExport();
        }
    }
}
