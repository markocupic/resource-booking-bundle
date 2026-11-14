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

// Fields
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_resourceTypes'] = ['Ressourcen-Typen', 'Wählen Sie mindestens 1 Ressourcen-Typ aus.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_displayClientPersonalData'] = ['Name der buchenden Person anzeigen', 'Zeigt bei fremden Buchungen den Namen und die unten gewählten Angaben der buchenden Person an. Achtung: Diese Daten sind für alle eingeloggten Mitglieder sichtbar. Ohne diese Option erscheint «Anonym»; den eigenen Namen sieht jedes Mitglied immer.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_clientPersonalData'] = ['Angaben zur buchenden Person', 'Wählen Sie die Angaben aus, die alle eingeloggten Mitglieder sehen dürfen. Für den Namen Vor- und Nachname wählen. Welche Felder zur Auswahl stehen, wird in der Bundle-Konfiguration festgelegt (displayable_member_fields). Weitere Felder als der Name müssen im Template ergänzt werden.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_displayBookingDetails'] = ['Weitere Angaben zur Buchung anzeigen', 'Zeigt bei Buchungen die unten gewählten Angaben an, z. B. die Beschreibung. Achtung: Diese Daten sind für alle eingeloggten Mitglieder sichtbar.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_bookingDetailFields'] = ['Angaben zur Buchung', 'Wählen Sie die Angaben aus, die alle eingeloggten Mitglieder sehen dürfen. Welche Felder zur Auswahl stehen, wird in der Bundle-Konfiguration festgelegt (displayable_booking_fields). Das Template zeigt standardmässig die Beschreibung an; weitere Felder müssen im Template ergänzt werden.'];
$GLOBALS['TL_LANG']['tl_module']['resourceBooking_appConfig'] = ['App-Konfiguration auswählen', 'Erstellen Sie Ihre eigenen App-Konfigurationen in "config/config.yaml".'];
