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
$GLOBALS['TL_LANG']['tl_resource_booking']['booking_legend'] = 'Buchungs-Einstellungen';
$GLOBALS['TL_LANG']['tl_resource_booking']['module_legend'] = 'Modul-Einstellungen';
$GLOBALS['TL_LANG']['tl_resource_booking']['time_legend'] = 'Zeit-Einstellungen';

// Global operations
$GLOBALS['TL_LANG']['tl_resource_booking']['new'] = ['Neu', 'Neue Buchung erstellen.'];

// Fields
$GLOBALS['TL_LANG']['tl_resource_booking']['pid'] = ['Ressource', 'Die gebuchte Ressource.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['timeSlotId'] = ['Zeitpunkt', 'Das gebuchte Zeitfenster aus dem Buchungszeitplan der Ressource.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['bookingTime'] = ['Buchungseingang', 'Datum und Uhrzeit, an dem die Buchung eingegangen ist.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['member'] = ['Reserviert durch Frontend-Benutzer', 'Das Mitglied (Frontend-Benutzer), das die Buchung vorgenommen hat.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['isBlocked'] = ['Slot ist gesperrt (z.B. Ferienzeit)', 'Das Zeitfenster ist gesperrt und kann nicht gebucht werden, z. B. während der Ferien.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['title'] = ['Titel', 'Der Titel der Buchung. Er wird beim Buchen automatisch erzeugt.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['description'] = ['Beschreibung', 'Eine kurze Beschreibung der Buchung, z. B. der Zweck oder die Klasse.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['startTime'] = ['Startzeit', 'Beginn des gebuchten Zeitfensters. Der Wert wird aus dem Zeitfenster übernommen.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['endTime'] = ['Endzeit', 'Ende des gebuchten Zeitfensters. Der Wert wird aus dem Zeitfenster übernommen.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['bookingUuid'] = ['Buchungs-Id', 'Die eindeutige Kennung der Buchung. Wiederholungen einer Buchung haben dieselbe Kennung.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['itemsBooked'] = ['Gebuchte Einheiten', 'Die Anzahl der gebuchten Einheiten, wenn von der Ressource mehrere Stück verfügbar sind.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['moduleId'] = ['Frontend Modul', 'Das Frontend-Modul, über das die Buchung vorgenommen wurde.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['confirmed'] = ['Bestätigt', 'Die Buchung ist bestätigt. Ist die automatische Bestätigung bei der Ressource deaktiviert, muss die Buchung hier bestätigt werden.'];
$GLOBALS['TL_LANG']['tl_resource_booking']['upcoming'] = ['Bevorstehende Reservation', 'Die Buchung liegt in der Zukunft. Vergangene Buchungen werden per Cronjob automatisch als abgelaufen markiert.'];
