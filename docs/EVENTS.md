# 🧩 Events

Mit Events lässt sich das Buchungstool erweitern, ohne den Code der Erweiterung anzufassen. Alle Events liegen im Namespace `Markocupic\ResourceBookingBundle\Event` und werden über ihren **Klassennamen** registriert.

[← Zurück zur README](../README.md)

| Event | Ausgelöst | Abbrechen mit |
|---|---|---|
| [`PreBookingEvent`](#prebookingevent--postbookingevent) | vor dem Speichern einer Buchung | `StopBookingProcessException` |
| [`PostBookingEvent`](#prebookingevent--postbookingevent) | nach dem Speichern, vor dem Abschluss | `StopBookingProcessException` (macht die Buchung rückgängig) |
| [`PreCancellingEvent`](#precancellingevent--postcancellingevent) | vor dem Löschen einer Buchung | `StopBookingCancellationException` |
| [`PostCancellingEvent`](#precancellingevent--postcancellingevent) | nach dem Löschen, vor dem Abschluss | `StopBookingCancellationException` (macht die Stornierung rückgängig) |
| [`AjaxResponseEvent`](#ajaxresponseevent) | nach jeder Ajax-Anfrage des Wochenkalenders, vor dem Senden der Antwort | – |
| [`PrepareNotificationEvent`](#preparenotificationevent) | vor dem Versand einer Benachrichtigung (Buchung, Stornierung) | `$event->cancel()` |

> [!TIP]
> Buchung und Stornierung laufen in einer Datenbank-Transaktion. Wird in einem Event eine der beiden Exceptions geworfen, wird alles zurückgerollt, und das Mitglied sieht die Meldung der Exception.

## Listener registrieren

In einer Contao-Installation mit `autoconfigure` genügt das Attribut `#[AsEventListener]`. Weitere Konfiguration ist nicht nötig.

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 100)]
final class MyListener
{
    public function __invoke(PostBookingEvent $event): void
    {
        // ...
    }
}
```

Alternativ funktioniert natürlich auch eine klassische Klasse mit `EventSubscriberInterface`.

## PreBookingEvent / PostBookingEvent

Beide Events stellen dieselben Methoden bereit:

| Methode | Rückgabe |
|---|---|
| `getBookingCollection()` | die Buchungen (bei Serienbuchungen mehrere) als `Contao\Model\Collection` |
| `getUser()` | das eingeloggte Mitglied |
| `getRequest()` | der aktuelle Request (z. B. für eigene Formularfelder) |
| `getAjaxResponse()` | die Antwort an den Browser |
| `getAppState()` | der Zustand des Wochenkalenders (`AppState`: Modul, Seite, gewählte Ressource, Woche, `language` …) |

**Beispiel:** Buchung nur für Mitglieder einer bestimmten Gruppe zulassen und nach der Buchung eine E-Mail verschicken.

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\Date;
use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PreBookingEvent;
use Markocupic\ResourceBookingBundle\Exception\StopBookingProcessException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class BookingListener
{
    private const ALLOWED_GROUP = 3;

    #[AsEventListener]
    public function onPreBooking(PreBookingEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof FrontendUser || !\in_array(self::ALLOWED_GROUP, array_map('intval', (array) $user->groups), true)) {
            // Bricht die Buchung ab und zeigt die Meldung im Frontend an
            throw new StopBookingProcessException('Sie sind für diese Ressource nicht buchungsberechtigt.');
        }
    }

    #[AsEventListener]
    public function onPostBooking(PostBookingEvent $event): void
    {
        $user = $event->getUser();
        $bookings = $event->getBookingCollection();

        if (!$user instanceof FrontendUser || null === $bookings) {
            return;
        }

        foreach ($bookings as $booking) {
            $text = \sprintf(
                "Hallo %s\n\nSie haben «%s» am %s von %s bis %s gebucht.",
                $user->firstname,
                $booking->getRelated('pid')->title,
                Date::parse('d.m.Y', $booking->startTime),
                Date::parse('H:i', $booking->startTime),
                Date::parse('H:i', $booking->endTime),
            );

            mail($user->email, 'Ihre Buchung', $text);
        }
    }
}
```

> [!NOTE]
> Für den Versand von E-Mails in einem echten Projekt empfiehlt sich der Symfony Mailer oder das kostenpflichtige Benachrichtigungs-Bundle (siehe README).

### Fehler bei einzelnen Formularfeldern

Betrifft der Fehler ein Feld des Buchungsformulars, wirf eine `FieldValidationException` (eine
`StopBookingProcessException`) mit dem Feldnamen, wie er im `name`-Attribut des Feldes steht. Das Buchungsfenster
listet die Meldungen auf, markiert die Felder (`aria-invalid`, `aria-describedby`, roter Rahmen) und setzt den Fokus
auf das erste fehlerhafte Feld. Mehrere Felder lassen sich auf einmal melden:

```php
use Markocupic\ResourceBookingBundle\Exception\FieldValidationException;

// Ein Feld
throw FieldValidationException::forField('postal', 'Bitte geben Sie eine gültige Postleitzahl ein.');

// Mehrere Felder auf einmal
throw new FieldValidationException([
    'email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
    'phone' => 'Bitte geben Sie eine gültige Telefonnummer ein.',
]);
```

Das funktioniert auch mit mehreren Modulen auf derselben Seite: Die Felder werden nur im Formular des Moduls
markiert, das die Anfrage gesendet hat.

## PreCancellingEvent / PostCancellingEvent

Diese Events funktionieren genau wie die Buchungs-Events und bieten dieselben Methoden. `getBookingCollection()` liefert die Buchungen, die storniert werden (bei Serienbuchungen ggf. mehrere).

```php
#[AsEventListener]
public function onPreCancelling(PreCancellingEvent $event): void
{
    foreach ($event->getBookingCollection() ?? [] as $booking) {
        if ($booking->startTime < strtotime('+1 day')) {
            throw new StopBookingCancellationException('Buchungen können nur bis 24 Stunden vorher storniert werden.');
        }
    }
}
```

## PrepareNotificationEvent

Wird vor dem Versand einer Benachrichtigung ausgelöst (`Notification\NotificationSender`), im Wochenkalender nach einer Buchung bzw. Stornierung und bei Erweiterungen, die den Sender selbst aufrufen.

| Methode | Rückgabe |
|---|---|
| `getNotificationType()` | `BookingNotificationType::NAME` oder `CancellationNotificationType::NAME` |
| `getContext()` | `NotificationSender::CONTEXT_WEEK_CALENDAR` (`week_calendar`) oder der Kontext einer Erweiterung; ohne Angabe die Herkunft der Buchung (`tl_resource_booking.bookingSource`) |
| `getNotificationId()` / `setNotificationId()` | die in der Ressource gewählte Benachrichtigung (0 = keine) |
| `getTokens()` / `setTokens()` / `setToken()` | die Tokens |
| `getLocale()` / `setLocale()` | die Sprache der Benachrichtigung |
| `getBookings()`, `getResource()`, `getResourceType()`, `getMember()` | die Datensätze als Arrays (`getMember()` ist ohne Mitglied leer) |
| `cancel()` | die Benachrichtigung wird nicht versandt |

**Beispiel:** Bei Buchungen von Mitgliedern im Wochenkalender keine Benachrichtigung versenden und sonst ein Token ergänzen.

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use Markocupic\ResourceBookingBundle\Event\PrepareNotificationEvent;
use Markocupic\ResourceBookingBundle\Notification\NotificationSender;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class PrepareNotificationListener
{
    public function __invoke(PrepareNotificationEvent $event): void
    {
        if (NotificationSender::CONTEXT_WEEK_CALENDAR === $event->getContext()) {
            $event->cancel();

            return;
        }

        $event->setToken('booking_hotline', '041 123 45 67');
    }
}
```

## AjaxResponseEvent

Wird nach jeder Ajax-Anfrage des Wochenkalenders ausgelöst, sobald der Controller der Aktion erfolgreich geantwortet
hat (siehe [Eigene Ajax-Aktionen](#eigene-ajax-aktionen)) und bevor die Antwort ans Frontend geht. Damit lassen sich
der Antwort weitere Daten anhängen, z. B. für ein angepasstes Template.

| Methode | Zweck |
|---|---|
| `getAction()` | Aktion der Anfrage, z. B. `refreshDataRequest` (siehe [API](#api-des-wochenkalenders)) |
| `getAjaxResponse()` | Antwort (`setData()`, `getAll()`, Meldungen, Status) |
| `getAppState()` | Auswahl des Wochenkalenders (Modul, Seite, Ressource, Woche, Sprache) |
| `getRequest()` | Request an die API (nicht die Seite; die Seite liefert `getAppState()->page`) |

Die Daten der Wochenansicht (`rows`, `timeSlots`, `activeResource`, `activeWeek`, …) liefert die Aktion
`refreshDataRequest` (Laden, Aktualisieren, Wechsel von Ressource oder Woche, auch nach einer Buchung oder
Stornierung).

> [!WARNING]
> Alles in der Antwort ist für jeden Besucher der Seite sichtbar (Entwicklertools). Keine Personendaten fremder
> Mitglieder hinzufügen.

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class AddRoomNoticeListener
{
    public function __invoke(AjaxResponseEvent $event): void
    {
        if ('refreshDataRequest' !== $event->getAction()) {
            return;
        }

        $event->getAjaxResponse()->setData('notice', 'Bitte Zimmer nach Gebrauch lüften.');
    }
}
```

## API des Wochenkalenders

Das Buchungstool spricht mit einer API unter `/_rbb/api`. Jede Route ist einer Aktion zugeordnet; beantwortet wird
sie vom Ajax-Controller, der für diese Aktion registriert ist:

| Route | Aktion | Controller | Bedeutung |
|---|---|---|---|
| `GET /_rbb/api/calendar` | `refreshDataRequest` | `RefreshDataController` | Wochenansicht laden, aktualisieren, Ressource oder Woche wechseln |
| `POST /_rbb/api/bookings/validate` | `bookingFormValidationRequest` | `BookingFormValidationController` | Buchungsformular vor dem Absenden prüfen |
| `POST /_rbb/api/bookings` | `bookingRequest` | `BookingController` | Buchung ausführen |
| `DELETE /_rbb/api/bookings/{id}` | `cancelBookingRequest` | `CancelController` | Buchung stornieren (`?deleteRepetitions=1` mit Wiederholungen) |
| `POST /_rbb/api/actions/{action}` | eigene Aktion | eigener Controller | siehe unten |

**Zugang:** Beim Anzeigen des Moduls prüft Contao, ob der Besucher Seite und Modul sehen darf. Dabei erzeugt das Modul
bei jedem Seitenaufruf ein signiertes Zugangs-Token (Modul, Seite, Mitglied, gültig 8 Stunden), das jede Anfrage im
Header `X-Rbb-Access-Token` mitschickt. Bei jeder Anfrage wird zusätzlich geprüft, ob noch dasselbe Mitglied
angemeldet ist, die Seite veröffentlicht und für das Mitglied zugänglich ist (Mitgliedergruppen) und das Modul noch
existiert. Schreibende Anfragen brauchen zusätzlich das CSRF-Token von Contao (Header `X-Csrf-Token`). Antworten:
401, wenn das Token abgelaufen ist oder sich das Mitglied geändert hat, 403 ohne Zugang. Das Buchungstool zeigt dann
«Seite neu laden» an.

Die Auswahl (`resType`, `res`, `date`) steht bei `GET` und `DELETE` im Query-String, bei `POST` im Body.

## Eigene Ajax-Aktionen

Eine eigene Aktion ist ein eigener Controller: Klasse mit dem Attribut `#[AsAjaxController('…')]`, die
`ControllerInterface` implementiert (Service mit `autoconfigure`). Erreichbar ist sie über
`POST /_rbb/api/actions/{action}` mit denselben Zugangsprüfungen. Die Auswahl des Wochenkalenders liefert
`AppState::fromRequest($request)`. Jede Aktion kann nur von einem Controller beantwortet werden. Für eine Aktion ohne
Controller antwortet die Erweiterung mit HTTP 400 (`RBB.ERR.invalidAjaxAction`).

```php
<?php

declare(strict_types=1);

namespace App\AjaxController;

use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;

#[AsAjaxController('myCustomRequest')]
final class MyCustomController implements ControllerInterface
{
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $state = AppState::fromRequest($request);

        $ajaxResponse->setData('resourceId', $state->resource?->id);
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

        return $ajaxResponse;
    }
}
```

Im Template ruft man die Aktion über die Vue-Instanz auf, `apiFetch()` setzt Token und CSRF-Token:

```js
const formData = new FormData();
// Auswahl des Wochenkalenders (Ressourcentyp, Ressource, Woche)
vueInstance.appendAppState(formData);
const response = await vueInstance.apiFetch('POST', vueInstance.options.apiUrls.action.replace('__action__', 'myCustomRequest'), formData);
const json = await response.json();
```

Die Methoden der `AjaxResponse` im Überblick:

| Methode | Zweck |
|---|---|
| `setData($key, $value)` / `getData($key)` | beliebige Daten an das Frontend übergeben |
| `setStatus(…)` | `STATUS_SUCCESS`, `STATUS_WARNING` oder `STATUS_ERROR` |
| `setConfirmationMessage()`, `setInfoMessage()`, `setWarningMessage()`, `setErrorMessage()` | Meldungen im Buchungstool anzeigen |

[← Zurück zur README](../README.md)
