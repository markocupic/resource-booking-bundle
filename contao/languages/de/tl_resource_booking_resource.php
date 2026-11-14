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
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['title_legend'] = 'Titel-Einstellungen';
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['booking_restriction_legend'] = 'Buchungszeitraum und Bestätigung';

// Operations
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['bookings'] = 'Buchungen der Ressource mit ID %s ansehen';

// Global operations
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['new'] = ['Neu', 'Neue Ressource erstellen.'];

// Fields
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['published'] = ['Veröffentlichen', 'Die Ressource im Frontend anzeigen und zur Buchung freigeben.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['title'] = ['Bezeichnung', 'Geben Sie die Bezeichnung der Ressource ein, z. B. "Werkraum" oder "Beamer 1".'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['description'] = ['Beschreibung', 'Diese Beschreibung wird im Wochenkalender oberhalb der Tabelle angezeigt, z. B. für Hinweise zur Benutzung.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['timeSlotType'] = ['Buchungszeitplan', 'Wählen Sie den Buchungszeitplan mit den buchbaren Zeitfenstern aus.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['itemsAvailable'] = ['Verfügbare Stückzahl (Standard: 1).', 'Bitte geben Sie eine natürliche Zahl grösser als 0 ein.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['weeksBack'] = ['Wochen zurück', 'Geben Sie an, wie viele Wochen ab der aktuellen Woche zurückgeblättert werden kann (Standard: 10). Bei aktiviertem Cronjob werden ältere Buchungen gelöscht; bei 0 wird nichts gelöscht.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['weeksAhead'] = ['Wochen voraus', 'Geben Sie an, wie viele Wochen ab der aktuellen Woche vorausgeblättert und gebucht werden kann (Standard: 52).'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['addBookableUntil'] = ['Buchungen nur bis zu einem bestimmten Datum zulassen', 'Geben Sie an, ob die Ressource nur bis zu einem bestimmten Datum gebucht werden kann.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['autoConfirm'] = ['Buchungen automatisch bestätigen', 'Neue Buchungen dieser Ressource werden automatisch bestätigt. Ist die Option deaktiviert, muss die Buchung im Backend bestätigt werden.'];
$GLOBALS['TL_LANG']['tl_resource_booking_resource']['bookableUntil'] = ['Buchbar bis', 'Wählen Sie das Datum aus, bis zu dem die Ressource gebucht werden kann (inklusive).'];
