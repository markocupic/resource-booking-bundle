<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Legends
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['title_legend'] = 'Sperrzeit';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['date_legend'] = 'Zeitraum';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scope_legend'] = 'Geltungsbereich';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['publish_legend'] = 'Veröffentlichung';

// Global operations
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['new'] = ['Neu', 'Neue Sperrzeit erstellen.'];

// Fields
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['title'] = ['Interne Bezeichnung', 'Geben Sie eine Bezeichnung ein, z. B. "Herbstferien 2026". Sie wird nur im Backend angezeigt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['description'] = ['Hinweis im Wochenkalender', 'Optionaler Text, der im Wochenkalender in den gesperrten Zeitfenstern angezeigt wird, z. B. "Herbstferien". Bleibt das Feld leer, werden die Zeitfenster ohne Text als gesperrt angezeigt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateFrom'] = ['Von', 'Erster gesperrter Tag.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateTo'] = ['Bis', 'Letzter gesperrter Tag (inklusive). Leer lassen für eine unbefristete Sperrzeit, z. B. jedes Wochenende.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes'] = ['Gesperrte Zeiten', 'Optional: Nur an diesen Wochentagen und Uhrzeiten sperren, z. B. «Samstag» und «Sonntag» oder «Freitag 14:00–24:00». Leere Uhrzeiten bedeuten den ganzen Tag. Ohne Einträge ist der ganze Zeitraum gesperrt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_weekday'] = ['Wochentag'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_timeFrom'] = ['Gesperrt von (hh:mm)'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_timeTo'] = ['Gesperrt bis (hh:mm)'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scope'] = ['Gilt für', 'Wählen Sie aus, für welche Ressourcen die Sperrzeit gilt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resourceTypes'] = ['Ressourcen-Typen', 'Die Sperrzeit gilt für alle Ressourcen der ausgewählten Typen.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resources'] = ['Ressourcen', 'Die Sperrzeit gilt für die ausgewählten Ressourcen.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['published'] = ['Aktiv', 'Die Sperrzeit ist aktiv. Deaktivierte Sperrzeiten werden ignoriert.'];

// References
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['all'] = 'Alle Ressourcen';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resourceTypes'] = 'Ausgewählte Ressourcen-Typen';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resources'] = 'Ausgewählte Ressourcen';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['unlimited'] = 'unbefristet';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['daily'] = 'Täglich';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['monday'] = 'Montag';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['tuesday'] = 'Dienstag';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['wednesday'] = 'Mittwoch';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['thursday'] = 'Donnerstag';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['friday'] = 'Freitag';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['saturday'] = 'Samstag';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['sunday'] = 'Sonntag';
