# resource-booking-bundle
Mit diesem Modul für Contao kann eine Online-Ressourcenverwaltung betrieben werden. 
Das Modul wurde für eine Schule entwickelt, wo ein Raumreservations-System benötigt wurde. Natürlich kann das Plugin auch im Zusammenhang mit anderen Ressourcen betrieben werden. 

Nach der Installation mit dem Contao Manager müssen:
* Mindestens 1 Reservations-Zeitfenster-Typ erstellt werden.
* Danach darin die Reservations-Zeitfenster im Zeitformat H:i (08:00 bis 08:45) erstellt werden.
* Ressourcen-Typen und Ressourcen erstellt werden.
* Mitglieder angelegt werden.

Das Tool setzt auf vue.js, fontawesome und bootstrap 4.1 auf. Die benötigten Ressourcen werden im Template eingebunden.
Zudem muss jQuery muss im Layout eingebunden sein.

Anm: Bei der Installation wird [codefog/contao-haste](https://github.com/codefog/contao-haste) mitinstalliert.

![Alt text](src/Resources/public/screenshot/screenshot.png?raw=true "Buchungstool im Frontend-Ansicht")

![Alt text](src/Resources/public/screenshot/screenshot2.png?raw=true "Buchungstool im Frontend-Ansicht")

