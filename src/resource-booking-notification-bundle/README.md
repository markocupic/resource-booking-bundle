<img src="./src/Resources/public/logo.png" width="300">


# Resource Booking Notification Bundle
Dieses Bundle erweitert markocupic/resource-booking-bundle um die Möglichkeit bei Neubuchungen/Stornierungen über das **Notification Center von Terminal42** **Nachrichten** zu versenden.


## Installation über Contao Manager
Das Package kann als sogenanntes Artefakt **via Paketinstallation** im **Contao Manager** installiert werden. Danach muss das **Installtool** aufgerufen werden, weil zusätzliche Felder in die Datenbank geschrieben werden.
 

## Konfiguration
In den Frontend-Modul-Einstellungen können beim entsprechenden **Resource-Booking-Modul** die Benachrichtigungen aktiviert werden. 
Es besteht die Möglichkeit zwei Benachrichtigungen auszuwählen: 
- Benachrichtigung bei Buchung
- Benachrichtigung bei Stornierung


## In den beiden Benachrichtigungstypen können folgende Tags benutzt werden:

Tags, welche **die Buchung** betreffen (Felder aus tl_resource_booking):

```booking_details, booking_details_html, booking_description, booking_datim, booking_*```


Tags, welche **die Person**, die gebucht/stroniert hat  (Felder aus tl_member):

```booking_person_gender, booking_person_firstname, booking_person_lastname, booking_person_email, booking_person_phone, booking_person_street, booking_person_postal, booking_person_city, booking_person_*```


Tags, welche **die Ressource** betreffen  (Felder aus tl_resource_booking_resource):

```booking_resource_title, booking_resource_description, booking_resource_*```


Tags, welche **die Ressourcen-Kategorie** betreffen (Felder aus tl_resource_booking_resource_type):	
		
```booking_resource_type_title, booking_resource_type_description, booking_resource_type_*```


Der **Email-Body** könnte demnach so aussehen:

```
Hallo Admin

##booking_person_firstname## hat einen Raum gebucht.

Hier die Details zur Buchung vom ##booking_datim##:

##booking_details##
{if booking_description !=''}
Kommentar:
##booking_description##
{endif}

Raum: ##booking_resource_title##

Resourcen Typ: ##booking_resource_type_title##

Liebe Grüsse

Administrator 
```