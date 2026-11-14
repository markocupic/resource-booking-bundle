# Upgrade

## Version 5.* to 6.0

### Systemanforderungen

Das Bundle unterstützt nur noch Contao 5.7 und Contao 6 (`contao/core-bundle: ^5.7 || ^6.0`) mit Symfony 7.4 oder 8
und PHP 8.3 oder höher. Contao 4.13 und Contao 5.0–5.6 werden nicht mehr unterstützt.

Der Button-Callback für die Operation "Ressourcen-Typ bearbeiten" (`ResourceBookingResourceType::editHeader()`) wurde
entfernt; Contao stellt die Operation selbst dar.

### Buchungsregeln pro Ressource

Die Buchungsregeln werden neu pro Ressource (`tl_resource_booking_resource`) statt pro Frontend-Modul bzw.
App-Konfiguration eingestellt. Die Einstellungen befinden sich in den Ressourcen-Einstellungen in der Gruppe
"Buchungseinschränkungen".

| Bisher                                                                   | Neu (`tl_resource_booking_resource`)                                      |
|--------------------------------------------------------------------------|---------------------------------------------------------------------------|
| App-Konfiguration `intBackWeeks` (z.B. `-10`)                            | `weeksBack` "Wochen zurück" (z.B. `10`, positive Zahl)                    |
| App-Konfiguration `intAheadWeeks`                                        | `weeksAhead` "Wochen voraus"                                              |
| App-Konfiguration `autoConfirm`                                          | `autoConfirm` "Buchungen automatisch bestätigen"                          |
| `tl_module.resourceBooking_hideDays`, `resourceBooking_hideDaysSelection` | Sperrzeit mit gesperrten Wochentagen (siehe "Sperrzeiten")               |
| `tl_module.resourceBooking_addDateStop`                                  | `addBookableUntil` "Buchungen nur bis zu einem bestimmten Datum zulassen" |
| `tl_module.resourceBooking_dateStop`                                     | `bookableUntil` "Buchbar bis"                                             |

Standardwerte für neue Ressourcen: 10 Wochen zurück, 52 Wochen voraus, Buchungen automatisch bestätigen.

Aus den Frontend-Moduleinstellungen wurden die Felder "Buchungen nur für ausgewählte Wochentage zulassen" und
"Buchungsstop-Datum" entfernt. Die Auswahl der App-Konfiguration (`resourceBooking_appConfig`) bleibt im Modul.

### Wochentage pro Zeitfenster

Zeitfenster (`tl_resource_booking_time_slot`) haben das neue Feld `weekdays` "Wochentage". Damit lässt sich pro
Zeitfenster festlegen, an welchen Wochentagen es verfügbar ist, z. B. eine Lektion, die es am Mittwochnachmittag nicht
gibt. Die Migration `AddTimeSlotWeekdays` legt die Spalte an und setzt alle bestehenden Zeitfenster auf "alle
Wochentage", sodass sich am Wochenkalender nichts ändert.

Im Wochenkalender gilt:

* Eine Spalte wird ausgeblendet, wenn an diesem Wochentag kein Zeitfenster verfügbar ist.
* Eine Zeile wird ausgeblendet, wenn das Zeitfenster an keinem der angezeigten Wochentage verfügbar ist.
* Nur der Zeitplan entscheidet, welche Wochentage angezeigt werden. Gesperrte Wochentage (Sperrzeiten, z. B. das
  Wochenende) werden angezeigt; ihre Zeitfenster sind gesperrt. Bisher wurden die nicht buchbaren Wochentage
  ausgeblendet.
* Eine einzelne Zelle, deren Zeitfenster an diesem Tag nicht verfügbar ist, wird grau schraffiert mit "nicht verfügbar"
  angezeigt (`data-istimeslotavailable="false"`, Klasse `rbb-cell-not-available-label`, Übersetzung `RBB.notAvailable`).

Buchungsanfragen für ein Zeitfenster an einem nicht verfügbaren Wochentag werden mit `RBB.ERR.timeSlotNotAvailableOnWeekday`
abgelehnt. Die Slot-Daten im Frontend enthalten die neuen Schlüssel `isTimeSlotAvailable` und `timeSlotWeekdays`.

### Wochenbeginn im Zeitplan

Der Wochentag, mit dem der Wochenkalender beginnt, wird neu im Buchungszeitplan (`tl_resource_booking_time_slot_type`)
im Feld `beginWeek` "Wochenbeginn" festgelegt (Standard: Montag). Bisher wurde er in der App-Konfiguration
(`begin_week`) eingestellt. Damit können Ressourcen mit unterschiedlichen Zeitplänen auch unterschiedliche
Wochenanfänge haben. Beim Wechsel der Ressource wird die angezeigte Woche auf den Wochenbeginn des neuen Zeitplans
verschoben.

Die Migration `MoveBeginWeekToTimeSlotType` legt die Spalte an und überträgt `begin_week` (bzw. `beginnWeek`) der im
Modul gewählten App-Konfiguration auf die Zeitpläne der Ressourcen dieses Moduls. Wird ein Zeitplan in Modulen mit
unterschiedlichem Wochenbeginn verwendet, gilt der häufigste Wert; der Zeitplan wird in der Ausgabe der Migration
aufgeführt.

### Sperrzeiten (z. B. Ferien)

Neu gibt es im Backend unter "Ressourcen-Buchung" das Modul "Sperrzeiten" (Tabelle
`tl_resource_booking_blocked_period`). Eine Sperrzeit hat:

* eine interne Bezeichnung (z. B. "Herbstferien 2026"), die nur im Backend erscheint,
* optional einen "Hinweis im Wochenkalender" (z. B. "Herbstferien"), der in den gesperrten Zellen angezeigt wird,
* einen Zeitraum (Datum von–bis, inklusive; ohne Bis-Datum ist die Sperrzeit unbefristet),
* optional "Gesperrte Zeiten" (Row Wizard, Contao 5.7): pro Zeile Wochentag (oder "Täglich") sowie gesperrt von–bis
  (hh:mm, leer = ganzer Tag). Dann sind nur diese Wochentage bzw. Uhrzeiten im Zeitraum gesperrt, z. B. "Samstag" und
  "Sonntag" oder "Freitag 14:00–24:00". Ohne Zeilen ist der ganze Zeitraum gesperrt,
* einen Geltungsbereich (alle Ressourcen, ausgewählte Ressourcen-Typen oder ausgewählte Ressourcen).

Die bisherige Einstellung "Buchungen nur an ausgewählten Wochentagen zulassen" (`restrictWeekdays`/`allowedWeekdays`)
der Ressource wurde dadurch ersetzt. Die Migration `MoveWeekdayRestrictionsToBlockedPeriods` erstellt für jede
Kombination nicht buchbarer Wochentage eine unbefristete Sperrzeit ab heute, z. B. "Nicht buchbare Wochentage:
Samstag, Sonntag" mit dem Hinweis "Nicht buchbar" für alle Ressourcen mit diesen Wochentagen. Die Methode `AbstractSlot::isOnAllowedWeekday()`, die
Methode `ResourceBookingRestriction::isWeekdayAllowed()` und die Fehlermeldung `RBB.ERR.weekdayNotBookable` wurden
entfernt.

* Im Wochenkalender werden die betroffenen Zellen grau schraffiert und, falls vorhanden, mit dem Hinweis der Sperrzeit
  angezeigt (`data-isinblockedperiod="true"`, Klasse `rbb-cell-blocked-period-label`). Die Slot-Daten enthalten die
  neuen Schlüssel `isInBlockedPeriod`, `blockedPeriod` (nur die ID) und `blockedPeriodDescription`. Die interne
  Bezeichnung wird nicht ans Frontend übermittelt. In der Buchungsvorschau erscheint ohne Hinweis "Gesperrt"
  (`RBB.blocked`).
* Buchungen in einer Sperrzeit werden serverseitig mit `RBB.ERR.slotInBlockedPeriod` abgelehnt.
* Bestehende Buchungen in einer neuen Sperrzeit werden nicht gelöscht. Beim Speichern der Sperrzeit zeigt das
  Backend die Anzahl betroffener zukünftiger Buchungen an (`RBB.BE.bookingsInBlockedPeriod`).
* Die bisherigen gesperrten Buchungen (`tl_resource_booking.isBlocked`) funktionieren weiterhin, z. B. um einzelne
  Zeitfenster zu sperren.

Die Tabelle wird beim Datenbank-Update angelegt.

### Umbenannte Modulfelder

| Bisher (`tl_module`)                        | Neu (`tl_module`)                       |
|---------------------------------------------|-----------------------------------------|
| `resourceBooking_setBookingSubmittedFields` | `resourceBooking_displayBookingDetails` |
| `resourceBooking_bookingSubmittedFields`    | `resourceBooking_bookingDetailFields`   |

Die Migration `RenameModuleFields` benennt die Spalten beim Ausführen von `contao:migrate` um. Die Werte bleiben
erhalten.

### Migration der bestehenden Einstellungen

Beim Ausführen von `contao:migrate` übertragen zwei Migrationen die bisherigen Einstellungen auf alle Ressourcen der
im jeweiligen Modul ausgewählten Ressourcen-Typen:

* `MoveBookingRestrictionsToResource`: Wochentage und Buchungsstop-Datum aus `tl_module`; die Wochentage werden danach
  von `MoveWeekdayRestrictionsToBlockedPeriods` in Sperrzeiten umgewandelt
* `MoveAppConfigToResource`: Wochen zurück/voraus und automatische Bestätigung aus der App-Konfiguration des Moduls
* `MoveBeginWeekToTimeSlotType`: Wochenbeginn aus der App-Konfiguration des Moduls in den Zeitplan (siehe oben)
* `RenameModuleFields`: umbenannte Modulfelder (siehe oben)

Wird eine Ressource in mehreren Modulen mit unterschiedlichen Einstellungen verwendet, wird jeweils die restriktivere
Einstellung übernommen:

* Wochentage: Schnittmenge der erlaubten Wochentage (ist sie leer, die Vereinigungsmenge)
* Buchungsstop-Datum: das früheste Datum
* Wochen zurück/voraus: der kleinste Wert
* Automatische Bestätigung: nur wenn alle Module automatisch bestätigt haben

Die betroffenen Ressourcen werden in der Ausgabe der Migration aufgeführt und sollten im Backend kontrolliert werden.
Verweist ein Modul auf eine App-Konfiguration, die nicht (mehr) existiert, werden die Werte der früheren
Standard-Konfiguration übernommen (`-10`, `60`, `true`).

Die Migrationen legen die neuen Spalten bei Bedarf selbst an. Die alten Spalten `tl_module.resourceBooking_hideDays`,
`resourceBooking_hideDaysSelection`, `resourceBooking_addDateStop` und `resourceBooking_dateStop` werden vom
Schema-Abgleich zum Löschen vorgeschlagen. Die Löschung sollte erst nach
erfolgreicher Migration ausgeführt werden.

### Konfiguration

Es können weiterhin mehrere App-Konfigurationen (`markocupic_resource_booking.apps`) angelegt und im Frontend-Modul
ausgewählt werden. Eine App-Konfiguration enthält nur noch die erlaubten Formularfelder und den neuen Schalter
`auto_select_first_resource` (Standard: `true`): Ist noch weder Ressourcen-Typ noch Ressource gewählt (leere Session),
zeigt der Wochenkalender beim ersten Aufruf direkt die erste veröffentlichte Ressource des ersten Ressourcen-Typs des
Moduls an, der eine hat (Reihenfolge wie in den Auswahlfeldern). Bisher wurde nur automatisch gewählt, wenn es genau
einen Typ bzw. genau eine Ressource gab.

```yaml
# Bisher
markocupic_resource_booking:
    apps:
        default:
            beginnWeek: 'monday'
            intBackWeeks: -10
            intAheadWeeks: 60
            autoConfirm: true
            permittedUploadFields: [phone, email]
```

```yaml
# Neu
markocupic_resource_booking:
    apps:
        default:
            auto_select_first_resource: true
            permitted_form_fields: [phone, email]
```

| Bisher                  | Neu                                                            |
|-------------------------|----------------------------------------------------------------|
| `beginnWeek`            | Zeitplan: "Wochenbeginn" (wird von der Migration übertragen)   |
| `permittedUploadFields` | `permitted_form_fields`                                        |
| `intBackWeeks`          | Ressource: "Wochen zurück" (wird von der Migration übertragen) |
| `intAheadWeeks`         | Ressource: "Wochen voraus" (wird von der Migration übertragen) |
| `autoConfirm`           | Ressource: "Buchungen automatisch bestätigen" (Migration)      |

Die bisherigen Schlüssel sind veraltet (deprecated): `permittedUploadFields` wird automatisch auf
`permitted_form_fields` übertragen. `beginnWeek`, `begin_week` (aus einer früheren 6.0-Entwicklungsversion),
`intBackWeeks`, `intAheadWeeks` und `autoConfirm` werden nur noch von der Migration gelesen und können anschliessend
entfernt werden.

`bookingDescription`, `itemsBooked` und `bookingRepeatStopWeekTstamp` sind immer erlaubt und müssen nicht mehr in
`permitted_form_fields` aufgeführt werden.

Ist im Modul keine oder eine nicht (mehr) vorhandene App-Konfiguration ausgewählt, wird die App-Konfiguration
`default` verwendet.

### Templates und JavaScript

Die folgenden Schlüssel der an das Frontend übergebenen Daten wurden umbenannt ("beginn" → "begin"). Angepasste
Templates müssen entsprechend geändert werden:

| Bisher                                      | Neu                                        |
|---------------------------------------------|--------------------------------------------|
| `week.tstampBeginnWeek` (Wochenauswahl)     | `week.tstampBeginWeek`                     |
| `week.stringBeginnWeek` (Wochenauswahl)     | `week.stringBeginWeek`                     |
| `slot.beginnWeekTimestampSelectedWeek`      | `slot.beginWeekTimestampSelectedWeek`      |

Die Übersetzungsschlüssel `RBB.ERR.invalidUploadValueSubmitted` und `RBB.ERR.invalidUploadFieldSubmitted` heissen neu
`RBB.ERR.invalidFormValueSubmitted` und `RBB.ERR.invalidFormFieldSubmitted`.

Neuer Aufbau des Wochenkalenders:

* Neue Kopfzeile (`_header.html.twig`, Block `rbb_header`): links Ressourcen-Typ, Ressource und die angezeigten Tage
  der Woche (z. B. "KW 47 · Montag, 16. bis Freitag, 20. November 2026"), rechts der angemeldete Benutzer. Sie ersetzt
  im Modul-Template den Aufruf von `rbb_logged_in_user`. Der Block `rbb_logged_in_user` gibt neu
  "Angemeldet als **Name**" aus.
* Die Filterfelder haben sichtbare Beschriftungen (`RBB.filterResourceType`, `RBB.filterResource`, `RBB.filterWeek`).
* Die Beschreibung der Ressource steht neu unter den Filterfeldern, unter der Tabelle folgt die neue Legende
  (`week_calendar/_legend.html.twig`, Block `rbb_week_calendar_window__legend`, Übersetzungen `RBB.legend.*`).
  Zellen mit mindestens einer unbestätigten Buchung werden orange hinterlegt
  (`data-hasunconfirmedbookings="true"`), die Buchung zeigt "wartet auf Bestätigung" (`RBB.awaitingConfirmation`).
  Bisher wurde nur eine ausgebuchte Zelle mit unbestätigter Buchung orange angezeigt. `booking.confirmed` ist neu
  immer ein Boolean.
* Neue Daten: `activeWeek.calWeek`, `activeWeek.daySpan` und `weekdays[].tstamp`. Das Datumsformat der Kopfzeile
  lässt sich mit `RBB.daySpan`, `RBB.daySpanFormatDay`, `RBB.daySpanFormatDayMonth` und `RBB.daySpanFormatFull`
  anpassen. `activeResource` und `activeResourceType` werden immer übermittelt (leer, wenn nichts gewählt ist).

### Angezeigte Daten fremder Buchungen (Datenschutz)

Bisher liessen sich im Frontend-Modul alle Felder von `tl_member` (ausser `id`/`password`) und von
`tl_resource_booking` zur Anzeige auswählen, und das Frontend erhielt zusätzlich die vollständigen Buchungszeilen.
Alles, was mitgeschickt wird, ist für jedes eingeloggte Mitglied in den Entwicklertools des Browsers sichtbar.

Neu:

* Welche Felder im Modul zur Auswahl stehen, legt die Bundle-Konfiguration fest:

  ```yaml
  markocupic_resource_booking:
      displayable_member_fields: [firstname, lastname, company, city, phone]   # Standard
      displayable_booking_fields: [title, description]                         # Standard
  ```

  `password`, `secret`, `backupCodes`, `trustedTokenVersion` und `session` von `tl_member` können nicht konfiguriert
  werden (Konfigurationsfehler). Im Modul früher gewählte Felder, die nicht mehr erlaubt sind, werden ignoriert.
* Pro Buchung gehen nur noch `id`, `pid`, `itemsBooked`, `bookingTime`, `confirmed`, `canCancel`, `bookedByFullname`,
  `bookedByAnonymous`, die erlaubten Mitgliederfelder als `bookedBy<Feld>` und die erlaubten Buchungsfelder als
  `booking<Feld>` ans Frontend. Die übrigen Originalfelder (z. B. `member`, `title`, `description`, `bookingUuid`)
  werden nicht mehr mitgeschickt. Templates müssen `booking.bookingDescription` statt `booking.description`
  verwenden.
* Die Buchungsvorschau (`slotSelection`) enthält keine fremden Buchungen mehr (`slot.bookings` ist leer).
* Die Modulfelder heissen neu "Name der buchenden Person anzeigen" / "Angaben zur buchenden Person" und "Weitere
  Angaben zur Buchung anzeigen" / "Angaben zur Buchung" und weisen darauf hin, dass die Daten für alle eingeloggten
  Mitglieder sichtbar sind.
* Neue Methoden `Utils::getDisplayedMemberFields()` und `Utils::getDisplayedBookingFields()`, neue Konstanten
  `RbbConfig::RBB_DEFAULT_DISPLAYABLE_MEMBER_FIELDS`, `RBB_FORBIDDEN_MEMBER_FIELDS`,
  `RBB_DEFAULT_DISPLAYABLE_BOOKING_FIELDS` und `RBB_ALWAYS_SENT_BOOKING_FIELDS`.

### Bootstrap entfernt

Das Bundle ist nicht mehr von Bootstrap abhängig. `markocupic/contao-component-bootstrap` wurde aus den Abhängigkeiten
entfernt, und das Frontend-Modul bindet `bootstrap.min.css` und `bootstrap.bundle.min.js` nicht mehr ein. Bisher wurde
das Bootstrap-CSS auf der ganzen Seite geladen und konnte das Theme verändern. Das neue Stylesheet ist auf
`.mod_resourceBookingWeekcalendar` beschränkt.

Falls dein Theme selbst Bootstrap benötigt, musst du es nun selbst einbinden.

Alle Bootstrap-Klassen in den Templates wurden durch eigene Klassen ersetzt. Wer Templates überschrieben oder das
Modul im Theme über Bootstrap-Klassen gestaltet hat, muss diese anpassen:

| Bisher                                               | Neu                                                      |
|------------------------------------------------------|----------------------------------------------------------|
| `btn btn-primary` / `btn-secondary` / `btn-danger`   | `rbb-btn rbb-btn--primary` / `--secondary` / `--danger`  |
| `btn-sm`, `w-100` (bei Buttons)                      | `rbb-btn--sm`, `rbb-btn--block`                          |
| `btn btn-link` (Buchen/Stornieren in der Zelle)      | `rbb-btn-icon`                                           |
| `alert alert-info` / `-warning` / `-success` / `-danger` / `-error` | `rbb-alert rbb-alert--info` / `--warning` / `--success` / `--danger` |
| `form-group`, `form-control`                         | `rbb-form-group`, `rbb-form-control`                     |
| `form-check`, `form-check-input`, `form-check-label` | `rbb-form-check`, `rbb-form-check-input`, `rbb-form-check-label` |
| `visually-hidden`, `d-none` (Labels)                 | `rbb-visually-hidden`                                    |
| `d-flex` (Tabellenzeilen)                            | `rbb-layout-row`                                         |
| `row`/`col-*` (Filter, Buchungsformular)             | `rbb-filter-board`, `rbb-booking-form-grid` (CSS Grid)   |
| `bg-primary` (Kopfzeile Buchungsfenster)             | wird über `.rbb-booking-window-header` gestaltet         |
| Abstandsklassen `mt-*`, `mb-*`, `pt-*`, `pb-*`, `me-*`, `m-3`, `py-4` | entfernt, Abstände sind im Stylesheet definiert |

Die Vorschau der belegten Zeitfenster im Buchungsfenster ("Belegte Ressourcen anzeigen") verwendet statt Bootstrap
Collapse nun ein natives `<details>`/`<summary>`-Element. Die Blöcke
`rbb_booking_window__booking_form__booking_preview_button` und `rbb_booking_window__booking_form__booking_preview_table`
liegen deshalb innerhalb von `<details class="rbb-booking-preview">`, und der Button ist ein `<summary>`.

Weitere Klassenänderungen: `rbb_week_calendar_window__active_resource_description` heisst `rbb-active-resource-description`,
der Wrapper um Name und Anzahl einer Buchung in der Zelle hat neu die Klasse `rbb-booked-item-header`.

Das Aussehen lässt sich über CSS-Variablen anpassen (z. B. `--rbb-primary`, `--rbb-radius`), siehe README.

### Geändertes Verhalten

* Buchungsanfragen werden serverseitig strenger geprüft, damit auch manipulierte Ajax-Requests nichts buchen können,
  was im Wochenkalender nicht angeboten wird:
  * Die Zeitfenster-ID muss zu einem veröffentlichten Zeitfenster des veröffentlichten Buchungszeitplans der aktiven
    Ressource gehören, und Start- und Endzeit müssen auf den Tag genau zum Zeitfenster passen (auch bei
    Wiederholungen). Sonst: `RBB.ERR.invalidTimeSlot` ("Ungültiges Zeitfenster.").
  * `itemsBooked` muss eine ganze Zahl von 1 bis zur Stückzahl der Ressource sein. Sonst:
    `RBB.ERR.invalidItemsBooked`.
  * Systemfelder der Buchung (u. a. `isBlocked`, `confirmed`, `member`, `pid`, `timeSlotId`, `startTime`, `endTime`,
    `bookingUuid`) werden aus dem Frontend nicht angenommen, auch nicht, wenn sie in `permitted_form_fields`
    eingetragen sind. Gesperrte Buchungen (`isBlocked`) können nur noch im Backend angelegt werden.
  * Die Prüfung der übermittelten Felder liegt neu in `Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator`.
    Slots haben die neuen Methoden `isValidTimeSlot()` und `hasValidItemCount()`; `isBookable()` berücksichtigt beide.
  * Ressourcen ohne gültige Stückzahl (`itemsAvailable` leer, NULL oder 0) wären damit nicht mehr buchbar. Die
    Migration `EnsureItemsAvailable` setzt sie auf 1 und nennt die betroffenen Ressourcen.
* Die Wochentag- und Datumseinschränkungen sowie die Anzahl Wochen voraus werden nicht mehr nur im Frontend
  (Ausblenden), sondern auch beim Buchen serverseitig geprüft. Ungültige Buchungen werden mit den neuen
  Fehlermeldungen `RBB.ERR.outsideBookingPeriod` bzw. `RBB.ERR.slotInBlockedPeriod` abgelehnt.
* `SlotBooking::isBookable()` prüft neu auch den erlaubten Zeitraum. Bisher konnten per Ajax-Request Slots in der
  Vergangenheit oder nach dem Buchungsstop-Datum gebucht werden.
* Die Wochennavigation (erste/letzte erlaubte Woche) hängt von der aktiven Ressource ab und wird beim Wechsel der
  Ressource neu berechnet.
* Der Cronjob `PurgePastBookingsCron` löscht alte Buchungen pro Ressource anhand von `weeksBack`
  (bisher pro Modul anhand von `intBackWeeks`). Bei `weeksBack = 0` wird nichts gelöscht. Die Wochen werden ab dem
  Wochenbeginn des Zeitplans der Ressource gezählt.
* `DateHelper::getFirstDayOfWeek()` gab für ein Datum, das selbst auf den Wochenbeginn fällt, den Wochenbeginn der
  Vorwoche zurück. Dieser Fehler ist behoben.
* `DateHelper::getFirstDayOfCurrentWeek()` lieferte bei einem anderen Wochenbeginn als Montag einen falschen Tag
  (bei Sonntag z. B. den Sonntag am Ende statt am Anfang der Woche). Die Methode verwendet neu
  `getFirstDayOfWeek()`.
* Zeitfenster und Buchungszeitpläne mit zukünftigen Buchungen lassen sich nicht mehr löschen. Contao zeigt eine
  Fehlermeldung mit der Anzahl betroffener Buchungen (`RBB.BE.deleteTimeSlotBlocked` bzw.
  `RBB.BE.deleteTimeSlotTypeBlocked`). Stattdessen kann das Zeitfenster bzw. der Zeitplan deaktiviert werden.
  Beim Löschen werden vergangene Buchungen nicht mehr mitgelöscht (bisher wurden alle Buchungen des Zeitfensters
  ohne Rückfrage gelöscht).
* Werden Start- oder Endzeit eines Zeitfensters geändert, werden nur noch Buchungen angepasst, die noch nicht
  begonnen haben. Der Titel der Buchung (`[Start - Ende]`) wird mit aktualisiert. Vergangene Buchungen bleiben
  unverändert.
* Beim Speichern einer Ressource erscheint ein Hinweis, wenn zukünftige Buchungen nicht mehr zum Buchungszeitplan
  oder zu den Wochentagen der Zeitfenster passen (`RBB.BE.futureBookingsNotMatchingTimetable`). Solche Buchungen werden
  im Wochenkalender nicht angezeigt.
* `ResourceBookingTimeSlot::removeChildRecords()` und `ResourceBookingTimeSlotType::removeChildRecords()` heissen
  neu `preventDeletionWithFutureBookings()`. Die DataContainer-Klassen `ResourceBookingTimeSlot`,
  `ResourceBookingTimeSlotType` und `ResourceBookingResource` erhalten zusätzlich den Translator (und das Framework)
  per Konstruktor.

### API-Änderungen

* `Utils::getAppConfig()` liefert nur noch `beginWeek` (bisher `beginnWeek`; neu aus dem Zeitplan der aktiven
  Ressource) und `permittedFormFields` (bisher `permittedUploadFields`) der im Modul ausgewählten App-Konfiguration.
  Statt einer Exception wird bei fehlender Konfiguration die App-Konfiguration `default` verwendet.
* Neue Methoden `Utils::getAppConfigByName()` (nur `permittedFormFields`) und `Utils::getBeginWeek()`.
* Der Konstruktor von `Utils` erhält den neuen Parameter `$appConfigs`.
* Der Konstruktor von `Initialize` erhält den neuen Service `WeekBoundaries`.
* Die Methoden von `PurgePastBookingsCron`
  `getModuleIdsWithBookings()` und `resolveAppConfigForModule()` wurden durch `getPurgeSettingsByResource()`
  ersetzt, `calculatePurgeLimit()` erwartet die Anzahl Wochen und den Wochenbeginn, `deleteExpiredBookings()` die
  Ressourcen-ID. Der Konstruktor erhält die App-Konfigurationen nicht mehr.
* `DateHelper::isWithinAllowedDateRange()` wurde entfernt. Die erlaubten Wochen werden mit `WeekBoundaries`
  berechnet und in der Session (`tstampFirstPermittedWeek`, `tstampLastPermittedWeek`) abgelegt.
* Neue Klassen: `Util\WeekBoundaries` und `Util\ResourceBookingRestriction`.
* Neue Methoden in `AbstractSlot`: `isOutsideBookingPeriod()`, `isTimeSlotAvailable()`, `isInBlockedPeriod()`,
  `isValidTimeSlot()` und `hasValidItemCount()`.
* Neue Konstanten in `RbbConfig`: `RBB_DEFAULT_WEEKS_BACK`, `RBB_DEFAULT_WEEKS_AHEAD`,
  `RBB_DEFAULT_BEGIN_WEEK` und `RBB_BASE_PERMITTED_FORM_FIELDS`.
* Neue Methode `ResourceBookingRestriction::getBeginWeek()`.

## Version 2.* to 3.0

### Templates

Die Templates wurden für eine bessere Übersichtlichkeit aufgeteilt. Vorher angepasste Custom-Templates müssen neu
geschrieben werden.
