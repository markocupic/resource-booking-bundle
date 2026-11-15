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

use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;

/*
 * Descriptions of the tokens in the auto suggester of the Notification Center
 * (translation key "nc_tokens.<notification type>.<token>").
 * Both notification types share the same tokens.
 */
foreach ([BookingNotificationType::NAME, CancellationNotificationType::NAME] as $type) {
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_email'] = 'E-Mail-Adresse der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_firstname'] = 'Vorname der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_lastname'] = 'Nachname der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_gender'] = 'Geschlecht der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_phone'] = 'Telefonnummer der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_street'] = 'Strasse der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_postal'] = 'PLZ der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_city'] = 'Ort der buchenden Person.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_person_*'] = 'Alle Felder der buchenden Person (tl_member).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_details'] = 'Liste aller gebuchten bzw. stornierten Slots (Text).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_details_html'] = 'Liste aller gebuchten bzw. stornierten Slots (HTML).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_description'] = 'Kommentar zur Buchung.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_datim'] = 'Datum und Uhrzeit der Buchung bzw. Stornierung.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_type_title'] = 'Bezeichnung des Ressourcen-Typs.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_type_description'] = 'Beschreibung des Ressourcen-Typs.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_type_*'] = 'Alle Felder des Ressourcen-Typs (tl_resource_booking_resource_type).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_title'] = 'Bezeichnung der Ressource.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_description'] = 'Beschreibung der Ressource.';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_resource_*'] = 'Alle Felder der Ressource (tl_resource_booking_resource).';
    $GLOBALS['TL_LANG']['nc_tokens'][$type]['booking_*'] = 'Alle Felder der ersten Buchung (tl_resource_booking).';
}
