# GasImpuls
Dieses Modul berechnet anhand vorher bestimmten Parametern den Verbrauch und die Kosten mithilfe einer Impulse-Instanz. Der Impulse kommt vom Gaszähler (z.B. BK.Gx,x oder ähnlich).
Das Modul ist nicht Herstllerabhängig und verarbeitet jeden Impuls, da der Impulsewert vorgegeben werden kann.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Berechnung von folgenden Verbrauchswerten:
	- Verbauch in kW/h und m3 für:
		- aktuellen Tag
		- letzten Tag
		- seit letzter Abrechnung
		- Schätzwert ungefähr zu erwartender Jahresverbrauch (Forecast)

	- Kosten in € für:
		- aktuellen Tag
		- letzten Tag
		- seit letzter Abrechnung
		- Schätzwert ungefähr zu erwartende Jahreskosten (Forecast)


### 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- Vorhandener Impulsegeber als Boolean-Variable

### 3. Software-Installation

* Über den Module Store das 'GasImpuls'-Modul installieren (derzeitig noch nicht aktiv).
* Alternativ über das Module Control folgende URL hinzufügen:
	- https://github.com/Burki24/IPS-GasImpulseRechner

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das Gerät 'Gas Impuls Verbrauchsanalyse' mithilfe des Schnellfilters gefunden werden.
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Instanz ID | ID der Impulsgeberinstanz
Impulswert| Der Impulswert in m3, der laut Aufschrift auf dem Zähler anzusetzen ist
Zählerstand | Aktueller Zählerstand zum Zeitpunkt der Modulinstallation
Ablesedatum | Datum der Abschlussablesung zur letzten Rechnung wird zur Berechnung des Gesamtverbrauchs seit Abrechnung benötigt.
Zählerstand in m3 | Der Zählerstand bei der letzten Abrechnung
kW/h auf Rechnung | Verbrauch im lwtzten Abrechnungszeitraum in kW/h. Findet sich auf der Abschlussrechnung
Brennwert | Der Brennwert findet sich i.d.R. auf der letzten Abschlussrechnung. Er stellt den Faktor von m3 zu kW/h dar. Sollte er in der Abschlussrechnung variieren, so ist der Mittelwert zu nehmen.
Z-Zahl | Zustandszahl findet sich auf der Abschlussrechnung und nimmt Einfluss auf die Umrechnung m3 zu kW/h.
kW/h Preis | Der aktuelle Kilowatt Preis des Anbieters
Grundpreis| Der Grundpreis (auch Arbeitspreis), der vom Anbieter verlangt wird
Zahlungszeitraum | Der Zeitraum, für den der eingetragene Grundpreis gilt (tgl., monatlich, Vierteljährlich, halbjährlich, jährlich) Der Zeitraum ist zwingend nötig, damit der Kostenaufwand auf den Tag heruntergebrochen werden kann. Dabei wird automatisch mit einbezogen, ob es sich aktuell um ein Schaltjahr handelt, oder nicht.
Anzahl der Abschlagsmonate | Es können 11 oder 12 Monate angegeben werden. Dies hat Einfluß auf den zu berchnenden Tagesgrundpreis <br> (Bsp.: bei einer monatlichen Zahlweise und einer Zahlungshöhe von 17,89€ ist der Tagesgrundpreis bei 12 Monatszahlungen 0,59€ bei 11 Monatszahlungen 0,54€) <br> Beachtet wird hierbei ebenso, ob es sich um ein Schaltjahr handelt, oder nicht <br> Für die Forecast-Berechnung benötigt diese Modul KEINE archivierten Daten.
Abschlagshöge | Zu zahlender Abschlag gemäß des laufenden Vertrags mit dem Anbieter (monatlicher Preis)
Monate | Faktor zur Heranziehung des Monats zur Errechnung des Forecast. 0.0 entspricht hier einem Auslassen des Monats, 1.0 entspricht der vollen Heranziehung. Somit können Monate mit weniger Heizleistung (z.B. Sommermonate) beachtet werden.
### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Ident | Name   | Typ     | Beschreibung
------ | -------------- | ------- | ------------
GCM_CostsSinceInvoice | Aktuell - Kosten seit letzter Abrechnung | Float | Kosten seit letzter Abschlussrechnung inklusive dem täglichen Grund-/Arbeitspreis
GCM_KWHSinceInvoice | Aktuell - kW/h seit letzter Abrechnung | Float | Der gemessene Verbrauch seit letzter Abrechnung in kW/h
GCM_DaysSinceInvoice | Aktuell - Tage seit letzter Abrechnung | Integer | Vergangene Tage seit letzter Abrechnung
GCM_CurrentConsumption | Aktuell - Verbrauch seit letzter Abrechnung in m3 | Float | Der aktuelle Verbrauch seit Abrechnung in m3
GCM_CounterValue       | Aktueller Zählerstand | Float       | Aktueller Zählerstand
GCM_InvoiceCounterValue       | Zählerstand bei letzter Abrechnung | Float       | Zählerstand bei Rechnugsablesung, wird benötigt um den Gesamtverbrauch seit Abrechnung zu ermitteln
GCM_LumpSumYear | Forecast - Errechnete Jahresabschlagshöhe | Float | Errechnet aus Abschlagshöhe und Anzahl der Abschlagsmonate die Jahreskosten
GCM_DaysTillInvoice | Forecast - Tage bis zur nächsten Abrechnung | Integer | Verbleibende Tage bis zur nächsten Abrechnung
GCM_LumpSumDiff | Forecast - Zu erwartende Differenz vom Abschlag | Float | Gibt eine Schätzung, ob die Abschlagszahlungen für die zu erwartenden Kosten deckend sind
GCM_CostsForecast | Forecast - Zu erwartende Kosten | Float | Errechnet aus den bisherigen Verbrauchsdaten (seit letzter Rechnungsstellung) die zu erwartenden Gesamtkosten bei nächster Abrechnung. Dieser Wert ändert sich anhand der zur Verfügung stehenden aktuellen Verbrauchswerte dynamisch. Inkludiert ist hier auch der Arbeitspreis (Grundpreis)
GCM_kwhForecast | Forecast - Zu erwartender Verbrauch | Float | Errechnet den für den aktuellen Abrechnungszeitraum zu erwartenden Gesamtverbrauch. Dieser Wert ändert sich gemäß den aktuellen Werten dynamisch. Hierbei wird die Gewichtung der Monate genutzt.
GCM_CostsYesterday       | Gestrige Kosten | Float       | Kosten des Vortages
GCM_ConsumptionYesterdayKWH       | Gestriger Verbrauch in kW/h | Float       | Verbrauch des Vortages in Kilowattstunden
GCM_ConsumptionYesterdayM3       | Gestriger Verbrauch in m3 | Float       | Verbrauch des Vortages in Qubikmeter
GCM_DayCosts       | Heutige Kosten | Float       | Aktuelle Tageskosten inklusive des Tagesgrundpreis (Arbeitspreis)
GCM_UsedKWH       | Heutiger Verbrauch in kw/h | Float        | Aktuell verbrauchte Kilowattstunden
GCM_UsedM3       | Heutiger Verbrauch in m3 | Float        | Aktuell verbrauchte Kubikmeter
GCM_BasePrice       | Basispreis | Float       | Grundpreis/Arbeitspreis auf den Tag berechnet
#### Profile

Name   | Typ
------ | -------
 GCM.Gas.kWh      | Float-Profil
 GCM.Days | Integer-Profil

#### Events

Ident   | Name | Zweck
----- | ----- | -----
GCM_EndOfDayTimer | Tagesabrechnung zum Ende des Tages | Erstellt um 23.59 Uhr den Tagesabschluss und beschreibt die Variablen für den gestrigen Tag. Der Timer ist im Webfront nicht sichtbar.

### 6. WebFront

Im Webfront können vorerst nur die Werte angezeigt werden und wenn Archivierung aktiviert ist der Verlauf.



### 7. PHP-Befehlsreferenz

Aktuell keine.