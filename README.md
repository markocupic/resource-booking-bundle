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
Mit verschiedenen Hooks kann das Modul erweitert werden.

### ResourceBookingPostBookingHook
Der *ResourceBookingPostBookingHook* wird nach dem Buchungsrequest getriggert. 

Hook in der listener.yml registrieren

```
services:
  Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\ResourceBookingPostBooking:
    tags:
    - { name: contao.hook, hook: resourceBookingPostBooking, method: onPostBooking, priority: 0 }
```

oder klassisch in der config.php:

```php
// Hooks
$GLOBALS['TL_HOOKS']['resourceBookingPostBooking'][] = [
    'Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\ResourceBookingPostBooking',
    'onPostBooking'
    ];
```

Die eigentliche Klasse:

```php
<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Contao\Date;
use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\EventSubscriber\AjaxRequestEventSubscriber;
use Model\Collection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ResourceBookingPostBooking.
 */
class ResourceBookingPostBooking
{
    public function onPostBooking(Collection $objBookingCollection, Request $request, ?FrontendUser $objUser, AjaxRequestEventSubscriber $objAjaxRequestEventSubscriber): void
    {
        // For demo usage only

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
Der *ResourceBookingAjaxResponse* wird vor dem Absenden der Response bei Ajax Anfragen getriggert. 

Hook in der listener.yml registrieren

```
services:
  Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\ResourceBookingAjaxResponse:
    tags:
    - { name: contao.hook, hook: resourceBookingAjaxResponse, method: onBeforeSend, priority: 0 }
```

oder klassisch in der config.php:

```php
// Hooks
$GLOBALS['TL_HOOKS']['resourceBookingAjaxResponse'][] = [
    'Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\ResourceBookingAjaxResponse',
    'onBeforeSend'
    ];
```

Die eigentliche Klasse:

```php
<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Markocupic\ResourceBookingBundle\Ajax\AjaxResponse;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/**
 * Class ResourceBookingAjaxResponse
 * @package Markocupic\ResourceBookingBundle\EventListener\ContaoHooks
 */
class ResourceBookingAjaxResponse
{
    /**
     * @param string $action
     * @param AjaxResponse $xhrResponse
     * @param ResourceBookingWeekcalendarController $objController
     */
    public function onBeforeSend(string $action, AjaxResponse &$xhrResponse, ResourceBookingWeekcalendarController $objController): void
    {
        if($action === 'fetchDataRequest')
        {
            // Do some stuff
        }

        if($action === 'applyFilterRequest')
        {
            // Do some stuff
        }

        if($action === 'jumpWeekRequest')
        {
            // Do some stuff
        }

        if($action === 'bookingRequest')
        {
            // Do some stuff
        }

        if($action === 'bookingFormValidationRequest')
        {
            // Do some stuff
        }

        if($action === 'cancelBookingRequest')
        {
            // Do some stuff
        }
    }
}
```
