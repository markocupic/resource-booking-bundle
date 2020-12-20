# resource-booking-bundle
Mit diesem Modul für Contao kann eine einfache Online-Ressourcenverwaltung betrieben werden. 
Das Modul wurde für eine Schule entwickelt, wo ein Zimmerreservations-System benötigt wurde. Natürlich kann das Plugin auch im Zusammenhang mit anderen Ressourcen betrieben werden. 

Nach der Installation mit dem Contao Manager müssen:
* Mindestens 1 Reservations-Zeitfenster-Typ erstellt werden.
* Danach darin die Reservations-Zeitfenster im Zeitformat H:i (08:00 bis 08:45) erstellt werden.
* Ressourcen-Typen erstellt werden.
* In jedem Ressourcen-Typ mindestens eine Ressource (z.B. Zimmer) erstellt werden.
* Mindestens 1 Mitglied (Frontend-Benutzer) angelegt werden. (Das Buchungsmodul wird nur bei eingeloggtem Benutzer angezeigt.)

Das Tool setzt auf [vue.js](https://vuejs.org/), [Fontawesome](https://fontawesome.com/) und [Bootstrap](https://getbootstrap.com/) auf. Die benötigten Libraries/Frameworks werden automatisch mitinstalliert und im Template eingebunden.
[jQuery](https://jquery.com/) muss im Seitenlayout aktiviert/eingebunden sein.

Anm: Bei der Installation wird neben den oben erwähnten Erweiterungen auch [codefog/contao-haste](https://github.com/codefog/contao-haste) mitinstalliert.

![Alt text](src/Resources/public/screenshot/screenshot.png?raw=true "Buchungstool im Frontend-Ansicht")

![Alt text](src/Resources/public/screenshot/screenshot2.png?raw=true "Buchungstool im Frontend-Ansicht")

## Hooks
Mit event subscribern kann die Applikation erweitert werden.

### ResourceBookingPostBookingHook
Der *rbb.event.post_booking* Event Subscriber wird nach dem Buchungsrequest getriggert. 

Klasse in der listener.yml registrieren

```
services:
  App\EventSubscriber\PostBookingEventSubscriber:
    tags:
    - { name: kernel.event_listener, event: rbb.event.post_booking, method: onPostBooking, priority: 10 }
```

Die eigentliche Klasse:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Contao\Date;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;

/**
 * Class PostBookingEventSubscriber.
 */
class PostBookingEventSubscriber
{
    public function onPostBooking(PostBookingEvent $objPostBookingEvent): void
    {
        // For demo usage only
        $objBookingCollection = $objPostBookingEvent->getBookingCollection();
        $objUser = $objPostBookingEvent->getUser();
        // $sessionBag = $objPostBookingEvent->getSessionBag();

        while ($objBookingCollection->next()) {
            if (null !== $objUser) {
                // Send notifications, manipulate database
                // or do some other insane stuff
                $strMessage = sprintf(
                    'Dear %s %s'."\n".'You have successfully booked %s on %s from %s to %s.',
                    $objUser->firstname,
                    $objUser->lastname,
                    $objBookingCollection->getRelated('pid')->title,
                    Date::parse('d.m.Y', $objBookingCollection->startTime),
                    Date::parse('H:i', $objBookingCollection->startTime),
                    Date::parse('H:i', $objBookingCollection->endTime)
                );
                mail(
                    $objUser->email,
                    utf8_decode((string) $objBookingCollection->title),
                    utf8_decode((string) $strMessage)
                );
            }
        }
    }
}

```

### ResourceBookingAjaxResponse
Der *rbb.event.XXX_request* werden vor dem Absenden der Response zurück an den Browser bei Ajax Anfragen getriggert. 

Klasse in der listener.yml registrieren

```
services:
  App\EventSubscriber\AjaxRequestEventSubscriber:
     tags:
     - { name: kernel.event_listener, event: rbb.event.fetch_data_request, method: onFetchDataRequest, priority: 10 }
     - { name: kernel.event_listener, event: rbb.event.apply_filter_request, method: onApplyFilterRequest, priority: 10 }
     - { name: kernel.event_listener, event: rbb.event.jump_week_request, method: onJumpWeekRequest, priority: 10 }
     - { name: kernel.event_listener, event: rbb.event.booking_request, method: onBookingRequest, priority: 10 }
     - { name: kernel.event_listener, event: rbb.event.booking_form_validation_request, method: onBookingFormValidationRequest, priority: 10 }
     - { name: kernel.event_listener, event: rbb.event.cancel_booking_request, method: onCancelBookingRequest, priority: 10 }
```

Die eigentliche Klasse:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;

/**
 * Class AjaxRequestEventSubscriber.
 */
class AjaxRequestEventSubscriber
{
    /**
     * @throws \Exception
     */
    public function onFetchDataRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    public function onApplyFilterRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    public function onJumpWeekRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    public function onBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    public function onBookingFormValidationRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    public function onCancelBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }
}

```
