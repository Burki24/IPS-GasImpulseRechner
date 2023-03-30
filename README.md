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

- Hardware-Tipp

	- __eigenes Gehäuse__ ([Dokumentation](SensorCase))
