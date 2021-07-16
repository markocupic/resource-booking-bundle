<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Days
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][0] = 'Mo';
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][1] = 'Di';
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][2] = 'Mi';
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][3] = 'Do';
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][4] = 'Fr';
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][5] = 'Sa';
$GLOBALS['TL_LANG']['DAYS_SHORTENED'][6] = 'So';

$GLOBALS['TL_LANG']['DAYS_LONG'][0] = 'Montag';
$GLOBALS['TL_LANG']['DAYS_LONG'][1] = 'Dienstag';
$GLOBALS['TL_LANG']['DAYS_LONG'][2] = 'Mittwoch';
$GLOBALS['TL_LANG']['DAYS_LONG'][3] = 'Donnerstag';
$GLOBALS['TL_LANG']['DAYS_LONG'][4] = 'Freitag';
$GLOBALS['TL_LANG']['DAYS_LONG'][5] = 'Samstag';
$GLOBALS['TL_LANG']['DAYS_LONG'][6] = 'Sonntag';

// Forms
$GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'] = 'KW %s/%s: %s - %s';
$GLOBALS['TL_LANG']['MSC']['bookingFor'] = 'Buchung für';

// Messages & Errors
$GLOBALS['TL_LANG']['MSG']['pleaseInsertValidBookingTime'] = 'Bitte geben Sie eine gültige Zeit in der Form hh:mm ein.';
$GLOBALS['TL_LANG']['MSG']['noResourceSelected'] = 'Es wurde keine Ressource ausgewählt';
$GLOBALS['TL_LANG']['MSG']['selectResourceTypePlease'] = 'Bitte wählen Sie einen Ressourcen-Typ aus.';
$GLOBALS['TL_LANG']['MSG']['selectResourcePlease'] = 'Bitte wählen Sie eine Ressource aus.';
$GLOBALS['TL_LANG']['MSG']['selectValidResourcePlease'] = 'Bitte wählen Sie eine gültige Ressource aus.';

// Frontend template
$GLOBALS['TL_LANG']['RBB']['1WeekBack'] = '1 Woche zurück';
$GLOBALS['TL_LANG']['RBB']['1WeekAhead'] = '1 Woche vor';
$GLOBALS['TL_LANG']['RBB']['close'] = 'Schliessen';
$GLOBALS['TL_LANG']['RBB']['cancelBooking'] = 'Soll Ihre Buchung storniert werden?';
$GLOBALS['TL_LANG']['RBB']['bookResourceRepetitions'] = 'Ressource buchen bis (Wiederholungen)';
$GLOBALS['TL_LANG']['RBB']['addDescription'] = 'Kurzbeschreibung eingeben (max 50 Zeichen).';
$GLOBALS['TL_LANG']['RBB']['abstract'] = 'Kurzbeschreibung';
$GLOBALS['TL_LANG']['RBB']['time'] = 'Uhrzeit';
$GLOBALS['TL_LANG']['RBB']['timeSpan'] = 'Zeitraum';
$GLOBALS['TL_LANG']['RBB']['available'] = 'verfügbar';
$GLOBALS['TL_LANG']['RBB']['alreadyBooked'] = 'bereits von Ihnen gebucht.';
$GLOBALS['TL_LANG']['RBB']['showOccupiedResources'] = 'Besetzte Ressourcen anzeigen';
$GLOBALS['TL_LANG']['RBB']['selectBookingTime'] = 'Bitte wählen Sie mindestens 1 Buchungszeitpunkt aus.';
$GLOBALS['TL_LANG']['RBB']['isNoMoreAvailable'] = 'ist im gewünschten Zeitraum nicht mehr buchbar.';
$GLOBALS['TL_LANG']['RBB']['windowIsClosingAutomatically'] = 'Dieses Fenster schliesst automatisch.';
$GLOBALS['TL_LANG']['RBB']['resource'] = 'Ressource';
$GLOBALS['TL_LANG']['RBB']['cancel'] = 'stornieren';
$GLOBALS['TL_LANG']['RBB']['bookResource'] = 'Ressource buchen';
$GLOBALS['TL_LANG']['RBB']['cancelResource'] = 'Buchung stornieren';
$GLOBALS['TL_LANG']['RBB']['book'] = 'buchen';
$GLOBALS['TL_LANG']['RBB']['selectResource'] = 'Ressource auswählen';
$GLOBALS['TL_LANG']['RBB']['loading'] = 'Lade...';
$GLOBALS['TL_LANG']['RBB']['loggedInAs'] = 'Angemeldet als';
$GLOBALS['TL_LANG']['RBB']['week'] = 'Woche';
$GLOBALS['TL_LANG']['RBB']['fullname'] = 'Name';
$GLOBALS['TL_LANG']['RBB']['selectResourceType'] = 'Kategorie auswählen';
$GLOBALS['TL_LANG']['RBB']['bookingIdAndUuid'] = 'Buchungs-Id/Uuid';
$GLOBALS['TL_LANG']['RBB']['deleteRepetitions'] = 'Wiederholungen mitlöschen.';
$GLOBALS['TL_LANG']['RBB']['invalidDate'] = 'Ungültiges Datum.';
$GLOBALS['TL_LANG']['RBB']['generalError'] = 'Es ist ein Fehler aufgetreten. Bitte überprüfen Sie die Verbindung.';
$GLOBALS['TL_LANG']['RBB']['error401'] = 'Es ist ein Fehler aufgetreten. Sie haben nicht die benötigen Rechte für diesen Seiteninhalt. Bitte versuchen Sie sich anzumelden.';
$GLOBALS['TL_LANG']['RBB']['pieces'] = 'Stück';

// Errors
$GLOBALS['TL_LANG']['RBB']['ERR']['invalidStartOrEndTime'] = 'Sie haben einen Slot mit ungültiger Start- oder Endzeit gewählt.';
$GLOBALS['TL_LANG']['RBB']['ERR']['slotNotBookable'] = 'Einer oder mehrere Slots sind nicht buchbar.';
$GLOBALS['TL_LANG']['RBB']['ERR']['generalBookingError'] = 'Beim Versuch die Ressource zu buchen ist ein Fehler aufgetreten.';
$GLOBALS['TL_LANG']['RBB']['ERR']['cancelingBookingNotAllowed'] = 'Sie besitzen nicht die nötigen Rechte, um diese Buchung zu stornieren.';
$GLOBALS['TL_LANG']['RBB']['ERR']['selectBookingDatesPlease'] = 'Bitte wählen Sie ein oder mehrere Buchungszeitfenster aus.';
$GLOBALS['TL_LANG']['RBB']['ERR']['resourceIsAlreadyFullyBooked'] = 'Die Ressource ist bereits ausgebucht.';
$GLOBALS['TL_LANG']['RBB']['ERR']['notEnoughItemsAvailable'] = 'Die gewünschte(n) Ressourcen sind ausgebucht oder davon sind nicht genügend Einheiten verfügbar.';

// Messages
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyBookedXItems'] = 'Für %s konnte(n) erfolgreich %s Buchung(en) angelegt werden.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBookingAndItsRepetitions'] = 'Ihre Buchung mit ID %s und weitere %s Wiederholunge(en) wurde(n) erfolgreich storniert.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBooking'] = 'Ihre Buchung mit ID %s wurde erfolgreich storniert.';
$GLOBALS['TL_LANG']['RBB']['MSG']['resourceAvailable'] = 'Die von Ihnen gewünschte Ressource ist im gewünschten Zeitraum noch buchbar.';


