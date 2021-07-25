<?php

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

// Global operations
$GLOBALS['TL_LANG']['tl_export_table']['new'][0] = 'Add new export';

// Legends
$GLOBALS['TL_LANG']['tl_export_table']['title_legend'] = 'Title settings';
$GLOBALS['TL_LANG']['tl_export_table']['settings'] = 'Settings';
$GLOBALS['TL_LANG']['tl_export_table']['deep_link_legend'] = 'Deep-Link settings';

// Fields
$GLOBALS['TL_LANG']['tl_export_table']['export_table'] = ['Export data from this table', 'Select a data table for the export please.'];
$GLOBALS['TL_LANG']['tl_export_table']['selected_fields'] = ['Select the fields for the export','Select the fields for the export please.'];
$GLOBALS['TL_LANG']['tl_export_table']['filterExpression'] = ['SQL "filter-expression"', 'Define filter as JSON-encoded Array -> [["tl_calendar_events.published=? AND tl_calendar_events.pid=?"],["1",6]] You can add insert tags as well: -> [["tl_member.id=?"],[{{user::id}}]]'];
$GLOBALS['TL_LANG']['tl_export_table']['sortBy'] = ['Sort by', 'Please add a sort by field.'];
$GLOBALS['TL_LANG']['tl_export_table']['sortByDirection'] = ['Sort by direction', 'Select sorting direction please.'];
$GLOBALS['TL_LANG']['tl_export_table']['exportType'] = ['Export type', 'Select the export type please.'];
$GLOBALS['TL_LANG']['tl_export_table']['arrayDelimiter'] = ['Array Delimiter', 'Please insert an array delimiter. Normaly "||".'];
$GLOBALS['TL_LANG']['tl_export_table']['activateDeepLinkExport'] = ['Activate Deep-Link export functionality'];
$GLOBALS['TL_LANG']['tl_export_table']['deepLinkExportKey'] = ['Deep-Link key', 'Add a key to protect the download from other users.'];
$GLOBALS['TL_LANG']['tl_export_table']['deepLinkInfo'] = ['Link-info'];

// Buttons
$GLOBALS['TL_LANG']['tl_export_table']['launchExportButton'] = 'Launch export process';

// Info text
$GLOBALS['TL_LANG']['tl_export_table']['deepLinkInfoText'] = 'Use this deep link to activate the table-export in your browser:';
