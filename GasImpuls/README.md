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

### 3. Software-Installation

* Über den Module Store das 'GasImpuls'-Modul installieren (derzeitig noch nicht aktiv).
* Alternativ über das Module Control folgende URL hinzufügen:
	- https://github.com/Burki24/IPS-GasImpulseRechner

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'GasImpuls'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
         |
         |

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
       |         |
       |         |

#### Profile

Name   | Typ
------ | -------
       |
       |

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`boolean BKJ_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`BKJ_BeispielFunktion(12345);`
