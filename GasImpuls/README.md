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
	- Verbauch in kW/h und M³ für:
		- aktuellen Tag
		- letzten Tag
		- seit letzter Abrechnung

	- Kosten in € für:
		- aktuellen Tag
		- letzten Tag
		- seit letzter Abrechnung


### 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- Vorhandener Impulsegeber als Boolean-Variable

### 3. Software-Installation

* Über den Module Store das 'GasImpuls'-Modul installieren (derzeitig noch nicht aktiv).
* Alternativ über das Module Control folgende URL hinzufügen:
	- https://github.com/Burki24/IPS-GasImpulseRechner

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Gas Impuls Verbrauchsanalyse'-Modul mithilfe des Schnellfilters gefunden werden.
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
Instanz ID | ID der Impulsgeberinstanz
Impulswert| Der Impulswert, der laut Aufschrift auf dem Zähler anzusetzen ist
Grundpreis| Der Arbeitspreis, der vom Anbieter verlangt wird
Zahlungszeitraum | Der Zeitraum, für den der Grundpreis gilt (tgl., monatlich, Vierteljährlich, halbjährlich, jährlich) Der Zeitraum ist zwingend nötig, damit der Kostenaufwand auf den Tag heruntergebrochen werden kann.
Brennwert | Der Brennwert findet sich i.d.R. auf der letzten Abschlussrechnung. Er stellt den Faktor von m³ zu kW/h dar. Sollte er in der Abschlussrechnung variieren, so ist der Mittelwert zu nehmen.
Zählerstand in m³ | Der Zählerstand bei der letzten Abrechnung
Ablesedatum | Datum der Abschlussablesung zur letzten Rechnung
Zählerstand | Aktueller Zählerstand zum Zeitpunkt der Modulinstallation
kW/h Preis | Der aktuelle Kilowatt Preis des Anbieters

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
GCM_UsedKWH       | Float        | Aktuell verbrauchte Kilowatt
GCM_UsedM3       | Float        | Aktuell verbrauchte m³
GCM_DayCosts       | Float       | Aktuelle Tageskosten
GCM_CounterValue       | Float       | Aktueller Zählerstand
GCM_CurrentConsumption       | Float       | Verbrauch seit letzter Ablesung in m³
GCM_CostsYesterday       | Float       | Kosten des Vortages
GCM_ConsumptionYesterdayKWH       | Float       | Verbrauch des Vortages in kW/h
GCM_ConsumptionYesterdayM3       | Float       | Verbrauch des Vortages in m³
GCM_BasePrice       | Float       | Grundpreis täglich
GCM_InvoiceCounterValue       | Float       | Zählerstand bei Rechnugsablesung
GCM_CostsSinceInvoice       | Float       | Kosten seit letzter Abschlussrechnung



#### Profile

Name   | Typ
------ | -------
 GCM.Gas.kWh      | Float-Profil



### 6. WebFront

Im Webfront können vorerst nur die Werte angezeigt werden und wenn Archivierung aktiviert ist der Verlauf.



### 7. PHP-Befehlsreferenz

Aktuell keine.



### 8. Zukünftige geplante Ergänzungen
- Forecast Errechnen für Verbrauch und Kosten
