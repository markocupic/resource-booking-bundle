<?php

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

// Global operations
$GLOBALS['TL_LANG']['tl_export_table']['new'] = ["Neuen Exportdatensatz anlegen", "Einen neuen Exportdatensatz anlegen"];

// Legends
$GLOBALS['TL_LANG']['tl_export_table']['title_legend'] = "Titel-Einstellungen";
$GLOBALS['TL_LANG']['tl_export_table']['settings'] = "Einstellungen";
$GLOBALS['TL_LANG']['tl_export_table']['deep_link_legend'] = "Deep-Link Einstellungen";

// Fields
$GLOBALS['TL_LANG']['tl_export_table']['title'] = ["Namen", "Geben Sie einen Namen ein."];
$GLOBALS['TL_LANG']['tl_export_table']['export_table'] = ["Datentabelle für Export auswählen", "Wählen Sie eine Tabelle für den Exportvorgang aus."];
$GLOBALS['TL_LANG']['tl_export_table']['fields'] = ["Felder für Exportvorgang auswählen.", "Wählen Sie die Felder für den Export aus."];
$GLOBALS['TL_LANG']['tl_export_table']['exportType'] = ['Export-Typ', 'Bitte wählen Sie einen Export-Typ aus.'];
$GLOBALS['TL_LANG']['tl_export_table']['filterExpression'] = ['SQL-Filter', 'Definieren Sie einen Filter in der Form eines JSON-kodierten Arrays -> [["tl_calendar_events.published=? AND tl_calendar_events.pid=?"],["1",6]] Auch Insert Tags sind möglich: -> [["tl_member.id=?"],[{{user::id}}]]'];
$GLOBALS['TL_LANG']['tl_export_table']['sortBy'] = ['Sortierung', 'Geben Sie das Feld an, nachdem sortiert werden soll.'];
$GLOBALS['TL_LANG']['tl_export_table']['sortByDirection'] = ['Sortierrichtung', 'Geben Sie die Sortierrichtung an.'];
$GLOBALS['TL_LANG']['tl_export_table']['arrayDelimiter'] = ['Array Trennzeichen', 'Geben Sie ein Trennzeichen ein, mit dem Arrays getrennt werden. Im Normalfall "||".'];
$GLOBALS['TL_LANG']['tl_export_table']['activateDeepLinkExport'] = ['Deep-Link Export aktivieren.', 'Deep-Link Export-Funktion aktivieren.'];
$GLOBALS['TL_LANG']['tl_export_table']['deepLinkExportKey'] = ['Deep-Link Schlüssel', 'Geben Sie einen Schlüssel ein, um den Download zu schützen.'];
$GLOBALS['TL_LANG']['tl_export_table']['deepLinkInfo'] = ['Link-Info'];

// Buttons
$GLOBALS['TL_LANG']['tl_export_table']['launchExportButton'] = "Exportvorgang starten";

// Info text
$GLOBALS['TL_LANG']['tl_export_table']['deepLinkInfoText'] = 'Benutzen Sie diesen Link, um die Tabellen-Exportfunktion in Ihrem Browser zu nutzen:';
