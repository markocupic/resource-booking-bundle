# Upgrade

## Version 5.* to 6.0

### Buchungsregeln pro Ressource

Die Buchungsregeln werden neu pro Ressource (`tl_resource_booking_resource`) statt pro Frontend-Modul bzw.
App-Konfiguration eingestellt. Die Einstellungen befinden sich in den Ressourcen-Einstellungen in der Gruppe
"Buchungseinschränkungen".

| Bisher                                                                   | Neu (`tl_resource_booking_resource`)                                      |
|--------------------------------------------------------------------------|---------------------------------------------------------------------------|
| App-Konfiguration `intBackWeeks` (z.B. `-10`)                            | `weeksBack` "Wochen zurück" (z.B. `10`, positive Zahl)                    |
| App-Konfiguration `intAheadWeeks`                                        | `weeksAhead` "Wochen voraus"                                              |
| App-Konfiguration `autoConfirm`                                          | `autoConfirm` "Buchungen automatisch bestätigen"                          |
| `tl_module.resourceBooking_hideDays`                                     | `restrictWeekdays` "Buchungen nur an ausgewählten Wochentagen zulassen"   |
| `tl_module.resourceBooking_hideDaysSelection`                            | `allowedWeekdays` "Buchbare Wochentage"                                   |
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

Ein Feld des Wochenkalenders ist nur buchbar, wenn sowohl das Zeitfenster als auch die Ressource
(`allowedWeekdays`) den Wochentag zulassen. Im Wochenkalender gilt:

* Eine Spalte wird ausgeblendet, wenn an diesem Wochentag kein Zeitfenster verfügbar ist.
* Eine Zeile wird ausgeblendet, wenn das Zeitfenster an keinem der angezeigten Wochentage verfügbar ist.
* Eine einzelne Zelle, deren Zeitfenster an diesem Tag nicht verfügbar ist, wird grau schraffiert mit "nicht verfügbar"
  angezeigt (`data-istimeslotavailable="false"`, Klasse `rbb-cell-not-available-label`, Übersetzung `RBB.notAvailable`).

Buchungsanfragen für ein Zeitfenster an einem nicht verfügbaren Wochentag werden mit `RBB.ERR.timeSlotNotAvailableOnWeekday`
abgelehnt. Die Slot-Daten im Frontend enthalten die neuen Schlüssel `isTimeSlotAvailable` und `timeSlotWeekdays`.

### Sperrzeiten (z. B. Ferien)

Neu gibt es im Backend unter "Ressourcen-Buchung" das Modul "Sperrzeiten" (Tabelle
`tl_resource_booking_blocked_period`). Eine Sperrzeit hat einen Titel (z. B. "Herbstferien"), einen Zeitraum
(Datum von–bis, inklusive), optional eine Uhrzeit (gilt für jeden Tag im Zeitraum) und einen Geltungsbereich
(alle Ressourcen, ausgewählte Ressourcen-Typen oder ausgewählte Ressourcen).

* Im Wochenkalender werden die betroffenen Zellen grau schraffiert und mit dem Titel der Sperrzeit angezeigt
  (`data-isinblockedperiod="true"`, Klasse `rbb-cell-blocked-period-label`). Die Slot-Daten enthalten die neuen
  Schlüssel `isInBlockedPeriod`, `blockedPeriod` und `blockedPeriodTitle`.
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

* `MoveBookingRestrictionsToResource`: Wochentage und Buchungsstop-Datum aus `tl_module`
* `MoveAppConfigToResource`: Wochen zurück/voraus und automatische Bestätigung aus der App-Konfiguration des Moduls
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
ausgewählt werden. Eine App-Konfiguration enthält nur noch den Wochenbeginn und die erlaubten Formularfelder, die
Schlüssel wurden umbenannt:

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
            begin_week: 'monday'
            permitted_form_fields: [phone, email]
```

| Bisher                  | Neu                                                            |
|-------------------------|----------------------------------------------------------------|
| `beginnWeek`            | `begin_week`                                                   |
| `permittedUploadFields` | `permitted_form_fields`                                        |
| `intBackWeeks`          | Ressource: "Wochen zurück" (wird von der Migration übertragen) |
| `intAheadWeeks`         | Ressource: "Wochen voraus" (wird von der Migration übertragen) |
| `autoConfirm`           | Ressource: "Buchungen automatisch bestätigen" (Migration)      |

Die bisherigen Schlüssel sind veraltet (deprecated), werden aber weiterhin gelesen: `beginnWeek` und
`permittedUploadFields` werden automatisch auf die neuen Schlüssel übertragen. `intBackWeeks`, `intAheadWeeks` und
`autoConfirm` werden nur noch von der Migration gelesen und können anschliessend entfernt werden.

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

* Die Wochentag- und Datumseinschränkungen sowie die Anzahl Wochen voraus werden nicht mehr nur im Frontend
  (Ausblenden), sondern auch beim Buchen serverseitig geprüft. Ungültige Buchungen werden mit den neuen
  Fehlermeldungen `RBB.ERR.outsideBookingPeriod` bzw. `RBB.ERR.weekdayNotBookable` abgelehnt.
* `SlotBooking::isBookable()` prüft neu auch den erlaubten Zeitraum. Bisher konnten per Ajax-Request Slots in der
  Vergangenheit oder nach dem Buchungsstop-Datum gebucht werden.
* Die Wochennavigation (erste/letzte erlaubte Woche) hängt von der aktiven Ressource ab und wird beim Wechsel der
  Ressource neu berechnet.
* Der Cronjob `PurgePastBookingsCron` löscht alte Buchungen pro Ressource anhand von `weeksBack`
  (bisher pro Modul anhand von `intBackWeeks`). Bei `weeksBack = 0` wird nichts gelöscht. Für den Wochenbeginn wird
  die App-Konfiguration `default` verwendet (oder die erste vorhandene).
* `DateHelper::getFirstDayOfWeek()` gab für ein Datum, das selbst auf den Wochenbeginn fällt, den Wochenbeginn der
  Vorwoche zurück. Dieser Fehler ist behoben.
* Zeitfenster und Buchungszeitpläne mit zukünftigen Buchungen lassen sich nicht mehr löschen. Contao zeigt eine
  Fehlermeldung mit der Anzahl betroffener Buchungen (`RBB.BE.deleteTimeSlotBlocked` bzw.
  `RBB.BE.deleteTimeSlotTypeBlocked`). Stattdessen kann das Zeitfenster bzw. der Zeitplan deaktiviert werden.
  Beim Löschen werden vergangene Buchungen nicht mehr mitgelöscht (bisher wurden alle Buchungen des Zeitfensters
  ohne Rückfrage gelöscht).
* Werden Start- oder Endzeit eines Zeitfensters geändert, werden nur noch Buchungen angepasst, die noch nicht
  begonnen haben. Der Titel der Buchung (`[Start - Ende]`) wird mit aktualisiert. Vergangene Buchungen bleiben
  unverändert.
* Beim Speichern einer Ressource erscheint ein Hinweis, wenn zukünftige Buchungen nicht mehr zum Buchungszeitplan
  oder zu den buchbaren Wochentagen passen (`RBB.BE.futureBookingsNotMatchingTimetable`). Solche Buchungen werden
  im Wochenkalender nicht angezeigt.
* `ResourceBookingTimeSlot::removeChildRecords()` und `ResourceBookingTimeSlotType::removeChildRecords()` heissen
  neu `preventDeletionWithFutureBookings()`. Die DataContainer-Klassen `ResourceBookingTimeSlot`,
  `ResourceBookingTimeSlotType` und `ResourceBookingResource` erhalten zusätzlich den Translator (und das Framework)
  per Konstruktor.

### API-Änderungen

* `Utils::getAppConfig()` liefert nur noch `beginWeek` (bisher `beginnWeek`) und `permittedFormFields`
  (bisher `permittedUploadFields`) der im Modul ausgewählten App-Konfiguration. Statt einer Exception wird bei
  fehlender Konfiguration die App-Konfiguration `default` verwendet.
* Neue Methode `Utils::getAppConfigByName()`.
* Der Konstruktor von `Utils` erhält den neuen Parameter `$appConfigs`.
* Der Konstruktor von `Initialize` erhält den neuen Service `WeekBoundaries`.
* Die Methoden von `PurgePastBookingsCron`
  `getModuleIdsWithBookings()` und `resolveAppConfigForModule()` wurden durch `getWeeksBackByResource()` und
  `getBeginWeek()` ersetzt, `calculatePurgeLimit()` erwartet die Anzahl Wochen, `deleteExpiredBookings()` die
  Ressourcen-ID.
* `DateHelper::isWithinAllowedDateRange()` wurde entfernt. Die erlaubten Wochen werden mit `WeekBoundaries`
  berechnet und in der Session (`tstampFirstPermittedWeek`, `tstampLastPermittedWeek`) abgelegt.
* Neue Klassen: `Util\WeekBoundaries` und `Util\ResourceBookingRestriction`.
* Neue Methoden in `AbstractSlot`: `isOnAllowedWeekday()` und `isOutsideBookingPeriod()`.
* Neue Konstanten in `RbbConfig`: `RBB_DEFAULT_WEEKS_BACK`, `RBB_DEFAULT_WEEKS_AHEAD` und
  `RBB_BASE_PERMITTED_FORM_FIELDS`.

## Version 2.* to 3.0

### Templates

Die Templates wurden für eine bessere Übersichtlichkeit aufgeteilt. Vorher angepasste Custom-Templates müssen neu
geschrieben werden.
