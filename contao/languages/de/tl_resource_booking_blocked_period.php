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
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['title'] = ['Titel', 'Geben Sie einen Titel ein, z. B. "Herbstferien". Er wird im Wochenkalender in den gesperrten Zellen angezeigt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['description'] = ['Beschreibung', 'Eine optionale interne Beschreibung.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateFrom'] = ['Von', 'Erster gesperrter Tag.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateTo'] = ['Bis', 'Letzter gesperrter Tag (inklusive).'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['addTimeRange'] = ['Nur bestimmte Uhrzeiten sperren', 'Sperrt nur die Zeitfenster innerhalb der angegebenen Uhrzeiten, z. B. nur nachmittags. Die Uhrzeiten gelten für jeden Tag im Zeitraum.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['timeFrom'] = ['Uhrzeit von', 'Geben Sie die Startzeit in der Form hh:mm ein, z. B. 12:00.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['timeTo'] = ['Uhrzeit bis', 'Geben Sie die Endzeit in der Form hh:mm ein, z. B. 18:00. Zeitfenster, die diese Zeitspanne überlappen, werden gesperrt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scope'] = ['Gilt für', 'Wählen Sie aus, für welche Ressourcen die Sperrzeit gilt.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resourceTypes'] = ['Ressourcen-Typen', 'Die Sperrzeit gilt für alle Ressourcen der ausgewählten Typen.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resources'] = ['Ressourcen', 'Die Sperrzeit gilt für die ausgewählten Ressourcen.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['published'] = ['Aktiv', 'Die Sperrzeit ist aktiv. Deaktivierte Sperrzeiten werden ignoriert.'];

// References
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['all'] = 'Alle Ressourcen';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resourceTypes'] = 'Ausgewählte Ressourcen-Typen';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resources'] = 'Ausgewählte Ressourcen';
