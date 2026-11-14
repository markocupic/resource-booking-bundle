# CLAUDE.md – resource-booking-bundle

Arbeitsgrundlage für Claude (und andere Entwickler) in diesem Bundle. Hier stehen die fachlichen Regeln, die
Architektur, die Konventionen und die Stolpersteine, die sich in der Entwicklung von Version 6 ergeben haben.
Bei Änderungen an Verhalten oder Konventionen diese Datei mitpflegen.

Projektsprache: Deutsch (Schweizer Rechtschreibung, «ss» statt «ß»). Code, Kommentare und Commit-Messages: Englisch.

## Überblick

- Contao-Bundle `markocupic/resource-booking-bundle`: Online-Reservation von Ressourcen (Zimmer, Geräte, Fahrzeuge)
  in Zeitfenstern, Frontend als Wochenkalender (Vue 3), Buchen nur für eingeloggte Mitglieder.
- Unterstützt: **Contao 5.7 und 6**, PHP ≥ 8.3, Symfony 7.4/8. Ältere Versionen nicht mehr berücksichtigen.
- Entwickelt in `C:\phpstorm\schuleettiswil\vendor\markocupic\resource-booking-bundle` (Projekt Schule Ettiswil).
- Zusatz-Bundle `markocupic/resource-booking-notification-bundle` (Notification Center, kostenpflichtig) bleibt auf
  Contao ^5.7, weil terminal42/notification_center Contao 6 noch nicht unterstützt.
- Doku: `README.md` (Anwender, kurz und gestylt), `docs/EVENTS.md` (Events für Entwickler), `UPGRADE.md` (alle
  Änderungen 5.x → 6.0, auf Deutsch, technisch).

## Datenmodell

| Tabelle | Zweck |
|---|---|
| `tl_resource_booking_time_slot_type` | **Zeitplan** (Buchungszeitplan). Feld `beginWeek` = Wochenbeginn (Standard `monday`). |
| `tl_resource_booking_time_slot` | Zeitfenster eines Zeitplans (`startTime`/`endTime`, `weekdays` = Wochentage, an denen es gilt). |
| `tl_resource_booking_resource_type` | Ressourcen-Typ / Kategorie (z. B. «Räume»). |
| `tl_resource_booking_resource` | Ressource. `timeSlotType` (Zeitplan), `itemsAvailable` (Stückzahl ≥ 1), `weeksBack`, `weeksAhead`, `addBookableUntil`/`bookableUntil`, `autoConfirm`, `description` (Hinweis im Frontend). |
| `tl_resource_booking_blocked_period` | **Sperrzeit**. `title` = interne Bezeichnung (nur Backend!), `description` = Hinweis im Wochenkalender (optional), `dateFrom` (Pflicht, Standard heute), `dateTo` (0 = unbefristet), `blockedTimes` (Row Wizard), `scope` (`all`/`resourceTypes`/`resources`), `published`. |
| `tl_resource_booking` | Buchung. `confirmed`, `isBlocked` (vom Admin gesperrt), `bookingUuid` (Serienbuchung), `itemsBooked`. |

App-Konfiguration (`config/config.yaml`, `markocupic_resource_booking.apps.<name>`) enthält nur noch
`permitted_form_fields` und `auto_select_first_resource` (Standard true: bei leerer Session zeigt der erste
Seitenaufruf die erste verfügbare Ressource, serverseitig in `Initialize::autoSelectFirstResource()`). Alles andere ist pro Ressource bzw. Zeitplan einstellbar. Veraltete Schlüssel
(`beginnWeek`, `begin_week`, `intBackWeeks`, `intAheadWeeks`, `autoConfirm`, `permittedUploadFields`) werden nur
noch von Migrationen gelesen.

## Fachliche Regeln

### Was im Wochenkalender angezeigt wird

- **Nur der Zeitplan entscheidet**, welche Wochentage (Spalten) und Zeitfenster (Zeilen) erscheinen:
  Spalte nur, wenn an diesem Wochentag mindestens ein Zeitfenster gilt; Zeile nur, wenn das Zeitfenster an
  mindestens einem angezeigten Wochentag gilt (`RefreshDataTrait::getVisibleWeekdays()`).
- Sperrzeiten blenden **nichts** aus: Ihre Zellen bleiben sichtbar, schraffiert, mit `description` als Text
  (leer = kein Text; in der Buchungsvorschau dann «Gesperrt», `RBB.blocked`).
- Zelle an einem Wochentag, an dem das Zeitfenster nicht gilt: schraffiert, «nicht verfügbar».
- Die Woche beginnt mit `beginWeek` des Zeitplans der **aktiven Ressource** (ohne Ressource: Montag).
  Beim Ressourcenwechsel wird die angezeigte Woche auf den neuen Wochenbeginn verschoben.
- «+ n» (noch verfügbare Stück) nur, wenn das Zeitfenster verfügbar und nicht gesperrt ist.

### Sperrzeiten

- Gilt von `dateFrom` bis `dateTo` (inklusive, tageweise); `dateTo = 0` → unbefristet.
- `blockedTimes`: Liste von Zeilen `{weekday: daily|monday…sunday, timeFrom: "hh:mm"|"", timeTo: "hh:mm"|""}`.
  Leere Uhrzeiten = ganzer Tag. Keine Zeilen = der ganze Zeitraum ist gesperrt. Ungültige Zeilen werden ignoriert.
- Beim Speichern: «bis» muss nach «von» liegen, Format 00:00–24:00 (`RBB.BE.timeToBeforeTimeFrom`,
  `RBB.BE.invalidBlockedTime`); vertauschte Daten werden getauscht; Hinweis auf betroffene zukünftige Buchungen.
- Logik: `Util\ResourceBookingRestriction::isInBlockedPeriod()` / `getBlockedTimes()`;
  `ResourceBookingBlockedPeriodModel::findPublishedRows()` ist pro Request gecacht.
- Früher gab es an der Ressource «Buchungen nur an ausgewählten Wochentagen» – das ist jetzt eine Sperrzeit.

### Buchen (serverseitige Prüfungen – nichts nur im Frontend verlassen!)

Ein Slot ist buchbar (`SlotMain::isBookable()` / `SlotBooking::isBookable()`), wenn **alle** zutreffen:

1. `isValidTimeSlot()`: Zeitfenster und Zeitplan veröffentlicht, Zeitplan = Zeitplan der Ressource, Start/Ende
   passen exakt zum Zeitfenster (auch bei Sommerzeit-Wechsel und Wiederholungen).
2. `hasValidItemCount()`: `itemsBooked` ganze Zahl von 1 bis `itemsAvailable`.
3. Nicht vom Admin gesperrt, im erlaubten Zeitraum (`weeksBack`/`weeksAhead`/`bookableUntil`, nicht in der
   Vergangenheit), Zeitfenster am Wochentag verfügbar, nicht in einer Sperrzeit, genug Stück frei.

Request-Validierung: `Validator\BookingRequestValidator`. Systemfelder (`PROTECTED_BOOKING_FIELDS`: id, pid,
member, isBlocked, confirmed, startTime, endTime, bookingUuid, …) werden nie aus dem Request übernommen, auch nicht
über `permitted_form_fields`. `isBlocked` kann nur im Backend gesetzt werden. Fehlerreihenfolge in
`BookingTrait`: invalidTimeSlot → invalidItemsBooked → outsideBookingPeriod → invalidStartOrEndTime →
timeSlotNotAvailableOnWeekday → slotInBlockedPeriod → ausgebucht.

Neue Buchungen: `confirmed` = `autoConfirm` der Ressource. Unbestätigte Buchungen: Zelle orange,
«wartet auf Bestätigung».

### Schutz bestehender Buchungen (Backend)

- Zeitfenster / Zeitpläne mit **zukünftigen** Buchungen lassen sich nicht löschen (Fehlermeldung + Redirect).
  Vergangene Buchungen werden beim Löschen nicht mehr mitgelöscht.
- Ändert man Start-/Endzeit eines Zeitfensters, werden nur zukünftige Buchungen angepasst (inkl. Titel `[..]`).
- Speichern einer Ressource: Hinweis, wenn zukünftige Buchungen nicht mehr zum Zeitplan passen.

### Cronjobs

- `PurgePastBookingsCron` (nur mit `purge_past_bookings_with_cron: true`): löscht pro Ressource Buchungen älter als
  `weeksBack` Wochen, gezählt ab Wochenbeginn des Zeitplans; `weeksBack = 0` → nichts löschen.

### Datenschutz im Frontend

- Alles im Ajax-Response ist für **jedes eingeloggte Mitglied** sichtbar (Entwicklertools). Fremde Buchungen deshalb
  nur mit explizit erlaubten Feldern ausliefern, nie ganze Zeilen (`row()`) von `tl_member`/`tl_resource_booking`.
- Erlaubte Felder: Bundle-Konfiguration `displayable_member_fields` (Standard firstname, lastname, company, city,
  phone) und `displayable_booking_fields` (Standard title, description); nur diese stehen im Modul zur Auswahl
  (`DataContainer\Module`), und nur Felder, die im Modul gewählt **und** konfiguriert sind, gehen raus
  (`Utils::getDisplayedMemberFields/BookingFields()`). `RBB_FORBIDDEN_MEMBER_FIELDS` (password, secret, backupCodes,
  trustedTokenVersion, session) sind nie erlaubt.
- Pro Buchung: `id`, `pid`, `itemsBooked`, `bookingTime`, `confirmed`, `canCancel`, `bookedByFullname`,
  `bookedByAnonymous`, `bookedBy<Feld>`, `booking<Feld>` – keine Originalfelder. Buchungsvorschau ohne fremde Buchungen.

## Architektur

- Ajax: Frontend sendet `action` (`refreshDataRequest`, `applyFilterRequest`, `bookingFormValidationRequest`,
  `bookingRequest`, `cancelBookingRequest`) → `AjaxRequestEvent` → `EventSubscriber\AjaxRequestEventSubscriber`
  (Priorität 1000) → Controller in `src/AjaxController` (Tag `markocupic_resource_booking.ajax_controller`).
- Daten für den Kalender: `AjaxController\Traits\RefreshDataTrait::getRefreshedData()`. `activeResource` und
  `activeResourceType` werden immer mitgeschickt (leer, wenn nichts gewählt), `activeWeek.calWeek`/`daySpan` für
  die Kopfzeile. `booking.confirmed` ist immer Boolean. `booking.bookedByAnonymous` ist true, wenn der Name
  des Buchenden nicht angezeigt werden darf (dann `bookedByFullname` = «Anonym», `RBB.anonymous`); der eigene Name
  ist immer sichtbar.
- Session-Bag: aktive Ressource (`res`), Typ (`resType`), `activeWeekTstamp`, erlaubte Wochen
  (`tstampFirstPermittedWeek`/`tstampLastPermittedWeek`, berechnet von `Util\WeekBoundaries`).
- Slots: `Slot\AbstractSlot::create()` sammelt alle Prüfergebnisse in `arrData`; `SlotMain` (Kalender) und
  `SlotBooking` (Buchung).
- Zentrale Regeln (statisch, testbar): `Util\ResourceBookingRestriction` (`getBeginWeek`, `isValidTimeSlot`,
  `isTimeSlotAvailableOnWeekday`, `isInBlockedPeriod`, `getWeeksBack/Ahead`, `getBookableUntil`,
  `isAutoConfirmEnabled`, `timeToSeconds`).
- Datum: `Util\DateHelper`. **Nie** `strtotime('<weekday> this week')` für den Wochenbeginn verwenden (PHP zählt
  die Woche immer ab Montag → bei Sonntag falscher Tag). Immer `DateHelper::getFirstDayOfWeek()`.
- `Utils::getAppConfig()` liefert `beginWeek` (aus dem Zeitplan der aktiven Ressource) und `permittedFormFields`.
- Events für Entwickler: siehe `docs/EVENTS.md` (Registrierung über Klassennamen, keine `rbb.event.*`-Namen mehr).

## Migrationen (`src/Migration`)

Laufen vor dem Schema-Abgleich; jede legt fehlende Spalten/Tabellen selbst an und läuft nur einmal.
Reihenfolge zwischen Migrationen nicht voraussetzen (`shouldRun()` wartet ggf. auf andere, z. B. auf
`RenameModuleFields`). Wichtige: `MoveAppConfigToResource`, `MoveBookingRestrictionsToResource`,
`MoveWeekdayRestrictionsToBlockedPeriods` (Titel «Nicht buchbare Wochentage: …», Hinweis «Nicht buchbar»),
`ConvertBlockedPeriodTimeRange`, `AddTimeSlotWeekdays`, `EnsureItemsAvailable`, `MoveBeginWeekToTimeSlotType`.
Alte Spalten erst nach erfolgreicher Migration löschen. Jede Verhaltensänderung in `UPGRADE.md` dokumentieren.

## Frontend

- Templates: `contao/templates/frontend_modules/mod_resourceBookingWeekcalendar.html.twig` bindet die Partials aus
  `templates/` per `{% use %}` ein (überschreibbar über Blöcke). Vue-Ausdrücke mit `[[ … ]]` (nicht `{{ }}`).
- Aufbau Wochenkalender (wie Design-Vorlage «Weich & freundlich»): Kopfzeile `_header.html.twig` (Kategorie als
  Kicker, Ressource als Titel, «KW 47 · Montag, 16. bis Freitag, 20. November 2026»; rechts «Angemeldet als
  **Name**») → Filter mit sichtbaren Labels (Kategorie / Ressource / Woche) → Hinweis der Ressource (grüner Kasten)
  → Tabelle → Legende (frei, meine Buchung, ausgebucht, noch nicht bestätigt, gesperrt).
- Zell-Zustände über `data-*`-Attribute in `_table.html.twig` (`data-isbookable`, `data-isbooked`,
  `data-iscancelable`, `data-isfullybooked`, `data-isinvaliddate`, `data-istimeslotavailable`,
  `data-isinblockedperiod`, `data-hasunconfirmedbookings`, `data-bookedbyanonymous` am Buchungseintrag, …).
  **`data-*`-Attribute immer komplett kleinschreiben:** Das Vue-Template steckt im HTML der Seite, der Browser
  wandelt Attributnamen ohnehin in Kleinbuchstaben um (CSS-Selektoren und `dataset` müssen dazu passen).
- Buchungsbutton: runder Plus-Kreis (`rbb_icon('fa-plus')`), Label nur für Screenreader.

### Stylesheet

- Quelle: `assets/styles/frontend.scss`. **Kein Bootstrap** – eigene Klassen, alle mit Präfix `rbb-`, alles unter
  `.mod_resourceBookingWeekcalendar`.
- Stil «weich & freundlich»: Systemschrift (`system-ui, -apple-system, 'Segoe UI', Roboto, …`), runde Ecken,
  Akzent Teal `#0f766e`, keine Schatten um die App.
- Farben/Abstände als CSS-Variablen (`--rbb-…`) mit `light-dark()`; Dark Mode folgt dem System bzw.
  `data-bs-theme` am `<html>`. Neue Farben immer als Variable anlegen, nie hart codieren.
- Selects: eigenes Caret (`--rbb-select-caret`, SVG-Data-URI, hell `#52606d` / dunkel `#a7b1bc`).
- Build: `npm run build` (Webpack Encore) → `public/css/frontend.<hash>.css` + `public/manifest.json` /
  `entrypoints.json`. Nach dem Build alte `frontend.*.css` löschen. `package.json` pinnt `webpack ~5.109.2`
  (5.112 bricht Encores copy-files-loader).

## Backend / DCA

- Jedes Feld hat eine kurze **und** eine lange Übersetzung (de und en), Sprachdateien in `contao/languages`.
- Datumsfelder mit Datepicker, Uhrzeiten als `hh:mm`, Standarddatum «heute» wo sinnvoll.
- Ressource: Legende `booking_restriction_legend` heisst «Buchungszeitraum und Bestätigung».
- Row Wizard (Contao ≥ 5.7) für `blockedTimes`.

## Tests & Qualität

- `composer unit-tests` (PHPUnit 12, `contao/test-case` 5.7 API: `createClassWithPropertiesStub`,
  `createAdapterStub/Mock`, `createContaoFrameworkStub/Mock`).
- `composer cs-fixer` (ECS, `markocupic/easy-coding-standard`). Hinweis: symplify/easy-coding-standard 13.3.17
  entfernte `ParamReturnAndVarTagMalformsFixer` → ggf. `<13.3.16` verwenden.
- `composer phpstan`.
- Neue Regeln immer mit Tests absichern (Restriction-, Slot-, Validator-, Migrations-Tests vorhanden).

## Arbeitsweise mit Claude (Cowork)

- Der Ordner ist über die Desktop-App verbunden; dort gibt es Node, aber kein PHP und Windows-`node_modules`
  (Encore läuft dort nicht). PHP-Lint und `npm run build` laufen deshalb in der Cloud-Arbeitsumgebung; die
  gebauten CSS-Dateien werden zurückgeschrieben. Packagist ist dort gesperrt → PHPUnit läuft nur lokal beim
  Entwickler; Logik wird mit kleinen PHP-Skripten geprüft.
- Nach Änderungen an DCA, Sprachdateien oder Templates: `vendor/bin/contao-console cache:clear`; nach
  Schema-Änderungen: `contao-console contao:migrate`.
- Commit-Messages: Englisch, Betreff im Imperativ, darunter Stichpunkte.

## Offene Punkte / Ideen

- «heute»-Markierung im Tageskopf (war in der Design-Vorlage, noch nicht umgesetzt).
- Notification-Bundle: Constraint `markocupic/resource-booking-bundle` auf `^6.0` setzen.
