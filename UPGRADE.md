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
`auto_select_first_resource` (Standard: `true`): Ist noch weder Ressourcen-Typ noch Ressource gewählt (erster Aufruf der Seite),
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

### Buchungsfenster als Dialog, Barrierefreiheit, kein Ton mehr

* Das Buchungs- bzw. Stornierungsfenster ist neu ein modaler `<dialog>` (`.rbb-booking-dialog`), der über dem
  Wochenkalender geöffnet wird. Der Fokus bleibt im Dialog, Esc schliesst ihn, danach steht der Fokus wieder auf dem
  auslösenden Button. Der Wochenkalender wird nicht mehr ausgeblendet (`v-if="mode === 'week-calendar'"` entfällt).
  Angepasste Modul-Templates müssen den Block `rbb_booking_window` entsprechend übernehmen (`ref="bookingDialog"`).
* Icon-Buttons haben eine Beschriftung für Screenreader (`aria-label` mit Datum und Zeit des Slots), die
  Schliessen-Buttons sind übersetzt, Meldungen werden mit `role="status"` angesagt.
* Eigene Buchungen sind zusätzlich mit einem Häkchen markiert (nicht nur farblich), auch in der Legende. Neues Feld
  `booking.isOwn` im Ajax-Response.
* Auf kleinen Bildschirmen (unter 576 px) zeigt der Wochenkalender einen Tag auf einmal. Über der Tabelle stehen
  Tages-Buttons (Block `rbb_week_calendar_window__day_tabs` in `_table.html.twig`, Übersetzung `RBB.filterDay`), ein
  Wischen nach links oder rechts wechselt den Tag, die Pfeiltasten ebenso. Angezeigt wird zuerst der heutige Tag, sonst
  der erste Tag der Woche. Die Tabelle hat dafür das Attribut `data-activedayindex`.
  Ebenfalls auf kleinen Bildschirmen: Die Filter stehen in zwei Zeilen (Woche mit Pfeilen, darunter Kategorie und
  Ressource nebeneinander), Kopfzeile und Abstände sind kompakter.
* Im Buchungsdialog ersetzt auf kleinen Bildschirmen eine Liste die Checkbox-Tabelle: Tages-Buttons mit der Anzahl
  gewählter Slots, darunter die Zeitfenster des gewählten Tags (Block `rbb_booking_window__booking_form__slot_list`
  in `_main_booking.html.twig`, Übersetzung `RBB.selected`). Geöffnet wird der Tag des angeklickten Slots. Die
  Info-Tabelle zeigt Bezeichnung und Wert untereinander, die Buttons bleiben unten im Dialog sichtbar.
* Der Wochenkalender ist für Screenreader eine Tabelle (`role="table"` mit Beschriftung «Wochenkalender: Ressource,
  Tage», `row`, `columnheader`, `rowheader`, `cell`). Jede Zelle wird so mit Tag und Uhrzeit angesagt. Vergangene
  Slots ohne Text erhalten unsichtbar «nicht buchbar» (`RBB.notBookable`). Auf kleinen Bildschirmen bleibt die
  Kopfzeile für Screenreader erhalten (nur visuell ausgeblendet, Klasse `rbb-layout-row-head`). Die Checkboxen im
  Buchungsdialog haben eine Beschriftung (Datum und Zeit).
* Kontraste nach WCAG 2.2 AA: Rahmen der Formularfelder (`--rbb-input-border-color`) mindestens 3:1, graue Texte
  (`--rbb-faint-color`) mindestens 4.5:1. Neue Variable `--rbb-divider-color` für Trennlinien.
* Der Buchungsdialog schliesst sich nach dem Buchen bzw. Stornieren nicht mehr automatisch (kein Zeitlimit,
  WCAG 2.2.1). Der Fokus springt auf «Schliessen». Die Option `autocloseWindowsAfter` und die Übersetzung
  `RBB.MSG.windowClosesAutomatically` wurden entfernt.
* Während einer Anfrage sind Wochenkalender und Buchungsformular mit `aria-busy` markiert. Hinweise der Filter
  (z. B. «Bitte Ressource wählen») stehen in einem Live-Bereich (`role="status"`). Mit der Systemeinstellung
  «Bewegung reduzieren» dreht sich das Lade-Icon nicht und es gibt keine Übergänge. Auf Touch-Geräten haben die
  Buttons «Buchen» und «Stornieren» in den Zellen eine Klickfläche von mindestens 44 × 44 px.
* Meldungen (Info, Warnung, Fehler) erscheinen nur im aktiven Fenster: bei offenem Buchungsdialog nur im Dialog,
  sonst unter den Filtern des Wochenkalenders.
* Fehler bei Formularfeldern werden dem Feld zugeordnet: Neue `Exception\FieldValidationException` (erweitert
  `StopBookingProcessException`, die dafür nicht mehr `final` ist) mit Feldname und Meldung, z. B. aus einem
  `PreBookingEvent`-Listener. Die Ajax-Antwort enthält dazu `fieldErrors` (Feld => Meldung, `AjaxResponse::
  setFieldError()`). Das Buchungsfenster listet die Meldungen auf (Block `rbb_booking_window__booking_form__field_errors`,
  `role="alert"`) und setzt am Feld `aria-invalid`, `aria-describedby` und die Klasse `rbb-is-invalid`; der Fokus
  springt auf das erste Feld. Ungültige Werte (`InvalidBookingRequestException`) und nicht ausgefüllte Pflichtfelder
  werden ebenfalls so gemeldet. `src/Exception` ist von der Service-Registrierung ausgenommen.
* Der Wochenkalender fragt die Daten nicht mehr ab, solange der Browser-Tab im Hintergrund ist; beim Zurückkehren
  und wenn das Gerät wieder online ist, werden sie sofort neu geladen.
* Kann der Server nicht erreicht werden (Netzwerkfehler), bleibt der Kalender sichtbar, und ein Hinweis erscheint
  (`RBB.ERR.connectionLost`, Block `rbb_status_message`); im Buchungsdialog erscheint `RBB.ERR.networkError`. Bisher
  drehte sich nur der Lade-Spinner. Die Texte werden über die neue Option `texts.networkError` an die App übergeben.
  Die `alert()`-Meldungen (veraltete Browser, ungültiger Meldungstyp) wurden entfernt.
* `tl_resource_booking_time_slot`: Die überflüssige Angabe `'operations' => 'all'` wurde entfernt (Contao verwendet
  die Standard-Operationen).
* Der Ton bei neuen Buchungen wurde entfernt (Optionen `audio` und `enableAudio`, Datei `audio/booking-alert.mp3`).

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
* Weitere Schutzmassnahmen gegen manipulierte Ajax-Requests:
  * **Keine Doppelbuchungen:** Der `BookingController` sperrt beim Buchen den Datensatz der Ressource
    (`SELECT … FOR UPDATE`) bis zum Ende der Transaktion. Buchen zwei Mitglieder gleichzeitig denselben Slot, wird
    die zweite Buchung mit "ausgebucht" abgelehnt. Bisher konnten beide die Verfügbarkeitsprüfung bestehen.
  * **Wiederholungen begrenzt:** `bookingRepeatStopWeekTstamp` wird auf die letzte buchbare Woche der Ressource
    begrenzt (`weeksAhead`, `bookableUntil`), höchstens 520 Wiederholungen pro Slot
    (`BookingTrait::MAX_REPETITIONS`). Ein manipulierter Wert erzeugte bisher beliebig viele Slots und Abfragen.
  * **Slot-Auswahl geprüft:** `bookingDateSelection` muss aus höchstens 100 Einträgen im Format
    `Zeitfenster-Start-Ende-Wochenbeginn` bestehen (`BookingRequestValidator::parseSlotSelection()`,
    `MAX_SELECTED_SLOTS`). Sonst: `RBB.ERR.invalidFormValueSubmitted`.
  * **Ressource des Moduls:** Gebucht werden können nur Ressourcen der im Modul gewählten Ressourcen-Typen
    (`AbstractController::isResourceOfModule()`). Bisher genügte eine veröffentlichte Ressource.
  * **Stornieren:** Vergangene Buchungen können nicht mehr storniert werden (`RBB.ERR.cancelingPastBookingNotPossible`);
    bisher wurde nur der Button im Frontend ausgeblendet. Beim Stornieren einer Serie werden vergangene Termine der
    Serie nicht mehr mitgelöscht.
  * Abgelehnte Buchungen (z. B. Slot inzwischen belegt) werden als `info` statt als `error` protokolliert. Fehler bei
    der Prüfung des Buchungsformulars (`bookingFormValidationRequest`) führen zu einer Fehlermeldung im Fenster statt
    zu einem HTTP-Fehler 500.
* Der Wochenkalender lädt die Buchungen der angezeigten Woche mit einer einzigen Abfrage (`SlotDataProvider`).
  Mitglied, Zeitfenster und Zeitplan werden pro Request nur einmal geladen. Bisher führte jede Zelle mehrere eigene
  Abfragen aus.
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
* Die Methoden von `PurgePastBookingsCron`
  `getModuleIdsWithBookings()` und `resolveAppConfigForModule()` wurden durch `getPurgeSettingsByResource()`
  ersetzt, `calculatePurgeLimit()` erwartet die Anzahl Wochen und den Wochenbeginn, `deleteExpiredBookings()` die
  Ressourcen-ID. Der Konstruktor erhält die App-Konfigurationen nicht mehr.
* `DateHelper::isWithinAllowedDateRange()` wurde entfernt. Die erlaubten Wochen werden mit `WeekBoundaries`
  berechnet.
* Neue Klassen: `Util\WeekBoundaries` und `Util\ResourceBookingRestriction`.
* Neue Methoden in `AbstractSlot`: `isOutsideBookingPeriod()`, `isTimeSlotAvailable()`, `isInBlockedPeriod()`,
  `isValidTimeSlot()` und `hasValidItemCount()`.
* Neue Konstanten in `RbbConfig`: `RBB_DEFAULT_WEEKS_BACK`, `RBB_DEFAULT_WEEKS_AHEAD`,
  `RBB_DEFAULT_BEGIN_WEEK` und `RBB_BASE_PERMITTED_FORM_FIELDS`.
* Neue Methode `ResourceBookingRestriction::getBeginWeek()`.
* Neue Klasse `Slot\SlotDataProvider` (Daten der Slots pro Request, `preloadBookings()`); `SlotFactory` und
  `AbstractSlot` erhalten sie als optionalen letzten Konstruktor-Parameter, `SlotFactory::preloadBookings()` ist neu.
  `AbstractSlot::getUserBooking()` liefert die Buchungszeile direkt (ohne erneutes Laden).
* `BookingTrait` erhält zusätzlich `WeekBoundaries` (Setter `setWeekBoundaries()`).
* Die Tabelle des Wochenkalenders wird neu von `WeekCalendar\WeekTableBuilder` erzeugt, die Daten einer Buchung von
  `WeekCalendar\BookingPresenter`.

### Benachrichtigungen im Bundle

Die Benachrichtigungen bei Buchung und Stornierung (bisher `markocupic/resource-booking-notification-bundle`) sind
jetzt Teil des Bundles. Das Bundle benötigt deshalb `terminal42/notification_center` (^2.0) und läuft vorerst nur mit
Contao 5, bis das Notification Center Contao 6 unterstützt.

* `markocupic/resource-booking-notification-bundle` muss entfernt werden (`composer remove …`), die Pakete schliessen
  sich gegenseitig aus. Benachrichtigungstypen, Felder (`tl_resource_booking_resource.bookingNotification`,
  `cancellationNotification`), Tokens und Migrationen sind unverändert, bestehende Benachrichtigungen funktionieren weiter.
* Klassen neu im Namespace `Markocupic\ResourceBookingBundle`: `NotificationType\{Booking,Cancellation}NotificationType`,
  `Notification\TokenBuilder`, `EventListener\SendNotificationListener`.
* Neu: `Notification\NotificationSender::send()` für Erweiterungen mit eigenem Buchungsablauf und das
  `PrepareNotificationEvent` (Benachrichtigung unterdrücken, Tokens, Benachrichtigung oder Sprache ändern), siehe
  `docs/EVENTS.md`.

### Modernes Twig-Template des Wochenkalenders

Das Frontend-Modul verwendet die modernen Fragment-Templates von Contao 5:

| Bisher | Neu |
|---|---|
| `contao/templates/frontend_modules/mod_resourceBookingWeekcalendar.html.twig` (erweitert `block_unsearchable`) | `contao/templates/twig/frontend_module/resource_booking_weekcalendar.html.twig` (erweitert `@Contao/frontend_module/_base.html.twig`) |
| eigene Templates `mod_resourceBookingWeekcalendar_<name>` | Varianten `templates/frontend_module/resource_booking_weekcalendar/<name>.html.twig` |

* Eigene Templates umbenennen und `{% extends '@Contao/frontend_module/resource_booking_weekcalendar.html.twig' %}`
  verwenden. Die Blöcke (`content`, `rbb_*`) und Variablen (`moduleKey`, `csrfToken`) sind unverändert.
* Der Wrapper trägt zusätzlich die Klasse `module-resourceBookingWeekcalendar`; die Klasse
  `mod_resourceBookingWeekcalendar` bleibt erhalten, eigenes CSS funktioniert weiter. Der Kalender wird weiterhin
  nicht indexiert (`searchable: false`).
* `getResponse()` des Controllers erhält ein `FragmentTemplate` (`$template->set()`).
* Die Migration `UseModernWeekcalendarTemplate` setzt Module, die ausdrücklich `mod_resourceBookingWeekcalendar`
  gewählt hatten, auf das Standard-Template zurück.

### Herkunft der Buchung

Neues Feld `tl_resource_booking.bookingSource` («Erfasst über»): `week_calendar` (Wochenkalender), `backend` oder
der Wert einer Erweiterung mit eigenem Buchungsablauf (Konstanten in `Booking\BookingSource`). Das Feld kann nicht
bearbeitet werden, es steht im Filter und in der Detailansicht. Die Migration `AddBookingSource` setzt bei
bestehenden Buchungen von Mitgliedern `week_calendar`, Buchungen ohne Mitglied bleiben leer (unbekannt).
Erweiterungen ergänzen die Bezeichnung in `$GLOBALS['TL_LANG']['tl_resource_booking']['bookingSources'][<Wert>]`.
`NotificationSender::send()` verwendet die Herkunft der Buchung als Kontext, wenn kein Kontext übergeben wird.

### Buchungssuche im Backend

Neu steht auf der Startseite des Backends eine Suche nach der Buchungs-ID (`tl_resource_booking.bookingUuid`) zur
Verfügung (Hook `parseBackendTemplate`, `EventListener\ContaoHooks\AddBookingSearchToWelcomeScreenListener` und
`Backend\BookingSearch`). Sichtbar für Administratoren und Benutzer mit Zugriff auf das Modul «Ressourcen». Das
Core-Template `be_welcome` wird nicht überschrieben. Direktlink: `/contao?rbb_booking_uuid=<ID>`, erzeugt von `Backend\BookingSearchUrlGenerator`.

### Kein Zustand mehr in der Session, kein Token in der URL

Der Wochenkalender speichert nichts mehr auf dem Server. Bisher hängte das Modul beim ersten Aufruf einen Token an
die URL (`?token_<moduleKey>=…`, mit Redirect) und legte Auswahl und Woche im Session-Bag ab. Neu schickt das
Frontend bei jedem Ajax-Request den Modul-Schlüssel und die Auswahl mit (`resType`, `res`, `date`); der Server prüft
sie und baut daraus den `AppState`, der am Request hängt. Folgen:

* Keine Redirects und keine Session mehr beim Aufruf der Seite (cachebar, keine Session-Cookies nur für den Kalender).
* Mehrere Browser-Tabs mit unterschiedlicher Auswahl stören sich nicht mehr. Die Auswahl bleibt pro Tab beim
  Neuladen erhalten (`sessionStorage`, Schlüssel `rbb:<Pfad und Query-String>:<moduleKey>`).
* Direktlinks: `?resType=<id>`, `?res=<id>` (wählt auch den Typ) und `?date=<Zeitstempel oder JJJJ-MM-TT>`.
  Ressourcen bzw. Typen, die nicht zum Modul gehören oder nicht veröffentlicht sind, werden ignoriert.
* Mehrere Wochenkalender auf einer Seite funktionieren weiterhin (Schlüssel `<Modul-ID>_<Index>`).

API-Änderungen:

* **Events:** `getSessionBag()` von `PreBookingEvent`, `PostBookingEvent`, `PreCancellingEvent` und
  `PostCancellingEvent` heisst neu `getAppState()` und liefert einen `AppState\AppState` (öffentliche, unveränderliche
  Eigenschaften `moduleKey`, `module`, `page`, `resourceType`, `resource`, `appConfig`, `activeWeekTstamp`,
  `firstPermittedWeekTstamp`, `lastPermittedWeekTstamp`, `language`). Statt `$sessionBag->get('language')` also
  `$event->getAppState()->language`. In anderen Listenern: `AppState::fromRequest($event->getRequest())`.
* **Entfernt:** `AppInitialization\Initialize`, `AppInitialization\Helper\{ModuleIndex,ModuleKey,TokenManager}`,
  `Session\Attribute\ArrayAttributeBag`, `Session\SessionFactory`, die Services `markocupic.rbb.session_bag` und
  `markocupic.rbb.session.factory`, `WeekBoundaries::storePermittedWeeks()` sowie `AjaxController\Traits\RefreshDataTrait`
  (neu der Service `WeekCalendar\CalendarDataBuilder::build(AppState, AjaxResponse)`).
* **Neu:** `AppState\AppState`, `AppState\AppStateFactory` (prüft die Auswahl, wählt beim ersten Request automatisch
  eine Ressource, begrenzt die Woche) und `AppState\ModuleKeyGenerator`.
* `Utils` erhält weder `RequestStack` noch den Namen des Session-Bags; `getAppConfig(ModuleModel|null, Resource|null)`
  und `getBeginWeek(Resource|null)` erwarten Modul bzw. Ressource. `getModuleModel()` und `getActiveResource()` wurden
  entfernt.
* Die Ajax-Controller und `BookingTrait` erhalten ihre Abhängigkeiten per Konstruktor (keine Setter mehr);
  `BookingTrait::isBookingPossible()` ist durch `getBookingErrorKey(SlotCollection)` ersetzt.
  `AjaxRequestEventSubscriber` liest den Request aus dem Event (kein `RequestStack` mehr).
* Die Konfiguration `markocupic_resource_booking.session` ist veraltet und wird nicht mehr verwendet.

### Ajax-Fehler als JSON

Die Ajax-Requests des Wochenkalenders beantwortet neu der Service `WeekCalendar\AjaxRequestHandler`; der
Modul-Controller ruft ihn nur noch auf. Fehler werden als JSON beantwortet statt mit einer HTML-Fehlerseite:

* Unbekannte `action` (kein Controller und kein Listener hat geantwortet): HTTP 400, `status: error`, Meldung
  `RBB.ERR.invalidAjaxAction`. `AjaxRequestEventSubscriber::get()` wirft dafür neu eine
  `Exception\UnknownAjaxActionException` (bisher `\Exception`).
* Andere Fehler: HTTP 500, `status: error`, Meldung `RBB.ERR.somethingWentWrong`; die Exception wird ins
  Contao-Systemlog geschrieben. Contao-`ResponseException`s (z. B. Redirects) werden weiterhin durchgereicht.
* Das Frontend zeigt die Meldung des Servers an (nicht beim automatischen Aktualisieren alle 15 Sekunden).
* Ajax-Requests eines anderen Wochenkalenders auf derselben Seite beantwortet das Modul mit einer leeren Antwort,
  statt sich vollständig zu rendern.
* Cache-Header der JSON-Antworten: `private, no-store` (bisher zusätzlich `max-age=0, must-revalidate`).
* Der Konstruktor von `ResourceBookingWeekcalendarController` erhält nur noch `AjaxRequestHandler`,
  `ContaoCsrfTokenManager`, `ModuleKeyGenerator`, `RequestStack` und `ScopeMatcher`; `getAjaxResponse()` wurde
  entfernt.

## Version 2.* to 3.0

### Templates

Die Templates wurden für eine bessere Übersichtlichkeit aufgeteilt. Vorher angepasste Custom-Templates müssen neu
geschrieben werden.
