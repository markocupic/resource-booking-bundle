# 🧩 Events

Mit Events lässt sich das Buchungstool erweitern, ohne den Code der Erweiterung anzufassen. Alle Events liegen im Namespace `Markocupic\ResourceBookingBundle\Event` und werden über ihren **Klassennamen** registriert.

[← Zurück zur README](../README.md)

| Event | Ausgelöst | Abbrechen mit |
|---|---|---|
| [`PreBookingEvent`](#prebookingevent--postbookingevent) | vor dem Speichern einer Buchung | `StopBookingProcessException` |
| [`PostBookingEvent`](#prebookingevent--postbookingevent) | nach dem Speichern, vor dem Abschluss | `StopBookingProcessException` (macht die Buchung rückgängig) |
| [`PreCancellingEvent`](#precancellingevent--postcancellingevent) | vor dem Löschen einer Buchung | `StopBookingCancellationException` |
| [`PostCancellingEvent`](#precancellingevent--postcancellingevent) | nach dem Löschen, vor dem Abschluss | `StopBookingCancellationException` (macht die Stornierung rückgängig) |
| [`AjaxRequestEvent`](#ajaxrequestevent) | bei jeder Ajax-Anfrage des Buchungstools | – |

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
| `getSessionBag()` | die Session-Daten des Buchungstools |

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

## AjaxRequestEvent

Das Buchungstool kommuniziert fast ausschliesslich per Ajax. Bei jeder Anfrage wird das `AjaxRequestEvent` ausgelöst. Die Erweiterung beantwortet es selbst mit Priorität **1000**.

Der Request enthält im Feld `action` eine dieser Aktionen:

| Aktion | Bedeutung |
|---|---|
| `refreshDataRequest` | Wochenansicht laden (auch beim Blättern) |
| `applyFilterRequest` | Ressource oder Woche wechseln |
| `bookingFormValidationRequest` | Buchungsformular vor dem Absenden prüfen |
| `bookingRequest` | Buchung ausführen |
| `cancelBookingRequest` | Buchung stornieren |

Je nach Priorität Ihres Listeners können Sie:

- **Antwort ergänzen** (Priorität < 1000): Ihr Listener läuft *nach* der Erweiterung und kann der fertigen Antwort weitere Daten hinzufügen.
- **Antwort ersetzen oder eigene Aktionen anbieten** (Priorität > 1000): Ihr Listener läuft *vor* der Erweiterung. Mit `stopPropagation()` wird die Standardverarbeitung übersprungen.

> [!IMPORTANT]
> Eigene Aktionen (z. B. `myCustomRequest`) **müssen** mit Priorität > 1000 und `stopPropagation()` beantwortet werden. Sonst sucht die Erweiterung einen passenden Controller, findet keinen und wirft einen Fehler.

```php
<?php

declare(strict_types=1);

namespace App\EventListener;

use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AjaxRequestListener
{
    // Läuft vor der Erweiterung (Priorität 1000)
    #[AsEventListener(priority: 1010)]
    public function onCustomRequest(AjaxRequestEvent $event): void
    {
        if ('myCustomRequest' !== $event->getRequest()->request->get('action')) {
            return;
        }

        // Standardverarbeitung überspringen
        $event->stopPropagation();

        $response = $event->getAjaxResponse();
        $response->setData('foo', 'bar');
        $response->setStatus(AjaxResponse::STATUS_SUCCESS);
    }

    // Läuft nach der Erweiterung und ergänzt die fertige Antwort
    #[AsEventListener(priority: 10)]
    public function onRefreshData(AjaxRequestEvent $event): void
    {
        if ('refreshDataRequest' !== $event->getRequest()->request->get('action')) {
            return;
        }

        $event->getAjaxResponse()->setData('notice', 'Bitte Zimmer nach Gebrauch lüften.');
    }
}
```

Die Methoden der `AjaxResponse` im Überblick:

| Methode | Zweck |
|---|---|
| `setData($key, $value)` / `getData($key)` | beliebige Daten an das Frontend übergeben |
| `setStatus(…)` | `STATUS_SUCCESS`, `STATUS_WARNING` oder `STATUS_ERROR` |
| `setConfirmationMessage()`, `setInfoMessage()`, `setWarningMessage()`, `setErrorMessage()` | Meldungen im Buchungstool anzeigen |

[← Zurück zur README](../README.md)
