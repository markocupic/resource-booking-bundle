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

## Event Subscriber
Mit event subscribern kann die Applikation an mehreren Stellen erweitert werden.

### Post booking event subscriber
Der *rbb.event.post_booking* Event wird nach dem Buchungs-Request ausgelöst. Mit einer Event-Subscriber-Klasse die auf den Event hört, können unmittelbar nach der Buchung Aktionen durchgeführt werden. Beispielsweise kann eine Benachrichtigung gesendet werden oder es können weitere Einträge in der Datenbank getätigt werden.

Dazu muss die Subscriber-Klasse, die auf den *rbb.event.post_booking* Event hört, in der listener.yml registriert werden:

```
services:
  App\EventSubscriber\PostBookingEventSubscriber:
    tags:
    - { name: kernel.event_listener, event: rbb.event.post_booking, method: onPostBooking, priority: 10 }
```

Weiter muss eine entsprechende Event-Subscriber-Klasse erstellt werden:

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

### Request event subscriber
Der *rbb.event.xml_http_request* Event wird bei Ajax-Anfragen vor dem Absenden der Response zurück an den Browser getriggert. Mit event subscribern kann die Response angepasst/erweitert werden. 

Dazu muss die Subscriber-Klasse, die auf den *rbb.event.xml_http_request* Event hört, in der listener.yml registriert werden:

```
services:
  App\EventSubscriber\AjaxRequestEventSubscriber:
    arguments:
    '@request_stack'
    tags:
    - { name: kernel.event_listener, event: rbb.event.on_xml_http_request, method: onXmlHttpRequest, priority: 10 }

```

Weiter muss eine entsprechende Event-Subscriber-Klasse erstellt werden:

```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AjaxRequestEventSubscriber.
 */
class AjaxRequestEventSubscriber
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * AjaxRequestEventSubscriber constructor.
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function onXmlHttpRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isXmlHttpRequest()) {
            $action = $request->request->get('action', null);

            if (null !== $action) {
                if (\is_callable([self::class, 'on'.ucfirst($action)])) {
                    $this->{'on'.ucfirst($action)}($ajaxRequestEvent);
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function onFetchDataRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $response = $ajaxRequestEvent->getAjaxResponse();
        $response->setData('foo', 'bla');
    }

    protected function onMyCustomRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Respond to custom ajax requests
    }

    protected function onApplyFilterRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    protected function onJumpWeekRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    protected function onBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    public function onBookingFormValidationRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }

    protected function onCancelBookingRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        // Do some stuff here
    }
}
```
