<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Weekdays
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['monday'] = 'Mo';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['tuesday'] = 'Di';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['wednesday'] = 'Mi';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['thursday'] = 'Do';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['friday'] = 'Fr';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['saturday'] = 'Sa';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['sunday'] = 'So';

$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['monday'] = 'Montag';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['tuesday'] = 'Dienstag';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['wednesday'] = 'Mittwoch';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['thursday'] = 'Donnerstag';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['friday'] = 'Freitag';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['saturday'] = 'Samstag';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['sunday'] = 'Sonntag';

// Forms
$GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'] = 'KW %s/%s: %s - %s';
$GLOBALS['TL_LANG']['MSC']['bookingFor'] = 'Buchung für';
$GLOBALS['TL_LANG']['RBB']['formLegendItems'] = 'Stückzahl auswählen';
$GLOBALS['TL_LANG']['RBB']['formHelpItems'] = 'Wählen Sie die Stückzahl aus, die Sie buchen möchten.';
$GLOBALS['TL_LANG']['RBB']['formLegendDescription'] = 'Kurzbeschreibung';
$GLOBALS['TL_LANG']['RBB']['formHelpDescription'] = 'Kurzbeschreibung eingeben (max 50 Zeichen).';
$GLOBALS['TL_LANG']['RBB']['formLegendRepetitions'] = 'Ressource buchen bis (Wiederholungen)';
$GLOBALS['TL_LANG']['RBB']['formOptionSelectResource'] = 'Ressource auswählen';
$GLOBALS['TL_LANG']['RBB']['formOptionSelectResourceType'] = 'Kategorie auswählen';

// Frontend template
$GLOBALS['TL_LANG']['RBB']['1WeekBack'] = '1 Woche zurück';
$GLOBALS['TL_LANG']['RBB']['1WeekAhead'] = '1 Woche vor';
$GLOBALS['TL_LANG']['RBB']['close'] = 'Schliessen';
$GLOBALS['TL_LANG']['RBB']['cancelBooking'] = 'Soll Ihre Buchung storniert werden?';
$GLOBALS['TL_LANG']['RBB']['time'] = 'Uhrzeit';
$GLOBALS['TL_LANG']['RBB']['timeSpan'] = 'Zeitraum';
$GLOBALS['TL_LANG']['RBB']['showOccupiedResources'] = 'Besetzte Ressourcen anzeigen';
$GLOBALS['TL_LANG']['RBB']['isAvailable'] = 'kann noch gebucht werden.';
$GLOBALS['TL_LANG']['RBB']['stillAvailable'] = 'noch verfügbar';
$GLOBALS['TL_LANG']['RBB']['resource'] = 'Ressource';
$GLOBALS['TL_LANG']['RBB']['cancel'] = 'stornieren';
$GLOBALS['TL_LANG']['RBB']['bookResource'] = 'Ressource buchen';
$GLOBALS['TL_LANG']['RBB']['cancelResource'] = 'Buchung stornieren';
$GLOBALS['TL_LANG']['RBB']['book'] = 'buchen';
$GLOBALS['TL_LANG']['RBB']['loading'] = 'Lade...';
$GLOBALS['TL_LANG']['RBB']['loggedInAs'] = 'Angemeldet als';
$GLOBALS['TL_LANG']['RBB']['week'] = 'Woche';
$GLOBALS['TL_LANG']['RBB']['fullname'] = 'Name';
$GLOBALS['TL_LANG']['RBB']['bookingIdAndUuid'] = 'Buchungs-Id/Uuid';
$GLOBALS['TL_LANG']['RBB']['deleteRepetitions'] = 'Wiederholungen mitlöschen.';
$GLOBALS['TL_LANG']['RBB']['invalidDate'] = 'Ungültiges Datum.';
$GLOBALS['TL_LANG']['RBB']['generalError'] = 'Es ist ein Fehler aufgetreten. Bitte überprüfen Sie die Verbindung.';
$GLOBALS['TL_LANG']['RBB']['error401'] = 'Es ist ein Fehler aufgetreten. Sie haben nicht die benötigen Rechte für diesen Seiteninhalt. Bitte versuchen Sie sich anzumelden.';
$GLOBALS['TL_LANG']['RBB']['pieces'] = 'Stück';
$GLOBALS['TL_LANG']['RBB']['anonymous'] = 'Anonym';


// Errors
$GLOBALS['TL_LANG']['RBB']['ERR']['invalidStartOrEndTime'] = 'Sie haben einen Slot mit ungültiger Start- oder Endzeit ausgewählt.';
$GLOBALS['TL_LANG']['RBB']['ERR']['slotNotBookable'] = 'Einer oder mehrere der gewünschten Slots sind bereits ausgebucht.';
$GLOBALS['TL_LANG']['RBB']['ERR']['generalBookingError'] = 'Beim Versuch die Ressource zu buchen ist ein Fehler aufgetreten.';
$GLOBALS['TL_LANG']['RBB']['ERR']['cancelingBookingNotAllowed'] = 'Sie besitzen nicht die nötigen Rechte, um diese Buchung zu stornieren.';
$GLOBALS['TL_LANG']['RBB']['ERR']['selectBookingDatesPlease'] = 'Bitte wählen Sie einen oder mehrere Slots aus.';
$GLOBALS['TL_LANG']['RBB']['ERR']['resourceIsAlreadyFullyBooked'] = 'Die Ressource ist bereits ausgebucht.';
$GLOBALS['TL_LANG']['RBB']['ERR']['notEnoughItemsAvailable'] = 'In einem oder mehreren Slots sind zu wenig Einheiten für Ihre Buchungsanfrage verfügbar.';
$GLOBALS['TL_LANG']['RBB']['ERR']['thisSlotHasNotEnoughItemsAvailable'] = 'Nicht genügend Einheiten verfügbar.';

// Messages
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyBookedXItems'] = 'Für %s konnte(n) erfolgreich %s Buchung(en) angelegt werden.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBookingAndItsRepetitions'] = 'Ihre Buchung mit ID %s und weitere %s Wiederholunge(en) wurde(n) erfolgreich storniert.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBooking'] = 'Ihre Buchung mit ID %s wurde erfolgreich storniert.';
$GLOBALS['TL_LANG']['RBB']['MSG']['resourceAvailable'] = 'Die von Ihnen gewünschte Ressource ist im gewünschten Zeitraum noch buchbar.';
$GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingTime'] = 'Bitte geben Sie eine gültige Zeit in der Form hh:mm ein.';
$GLOBALS['TL_LANG']['RBB']['MSG']['noResourceSelected'] = 'Es wurde keine Ressource ausgewählt';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectResourceTypePlease'] = 'Bitte wählen Sie einen Ressourcen-Typ aus.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectResourcePlease'] = 'Bitte wählen Sie eine Ressource aus.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectValidResourcePlease'] = 'Bitte wählen Sie eine gültige Ressource aus.';
$GLOBALS['TL_LANG']['RBB']['MSG']['windowClosesAutomatically'] = 'Dieses Fenster schliesst automatisch.';

