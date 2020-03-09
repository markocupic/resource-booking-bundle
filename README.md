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

