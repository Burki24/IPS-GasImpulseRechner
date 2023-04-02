[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-6.0%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Schnittcher/IPS-Zigbee2MQTT/workflows/Check%20Style/badge.svg)](https://github.com/Burki24/IPS-GasImpulseRechner/actions)

# Gas Verbrauchsanalyse

Verwertung eines Impuls, kommend von einer Boolean-Variable, welche den Impuls eines Gaszählers registriert. Der Impuls kann mittels eines Sensors bei vielen Gaszählern abgeholt werden. Entweder man nimmt den vom Hersteller zur Verfügung gestellten Sensor oder bereitgestelltem Signal, oder man kann sich auch einen eigenen Impuls-Sensor aus einem Reed-Kontakt (z.B. Zigbee-Fenstersensor) bauen.
Im Verzeichnis /SensorCase finden sich Bilder und eine stl-Druckdatei für die elster-Zähler BK-G2.5.

Folgende Module beinhaltet das Gas Verbrauchsanalyse Repository:

- __GasImpuls__ ([Dokumentation](GasImpuls))
	Verwertung eines Impuls, kommend von einer Boolean-Variable, welche den Impuls eines Gaszählers registriert.

	Errechnet daraus:
	- Verbrauchsdaten
	- Kosten
	- Forecast für die aktuelle Vertragszeit
			- Hier ist dabei zu beachten, das der Forecast aus den bisherigen Verbreuchsdaten seit Abrechnung ermittelt wird. Er schwankt also je nach Tagesaktuellem Gasverbrauch und stellt nur einen "in etwa" Richtwert dar
		- Differenz zur aktuellen Jahresabschlagsleistung


- Hardware-Tipp

	- __eigenes Gehäuse__ ([Dokumentation](SensorCase))

# Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)
