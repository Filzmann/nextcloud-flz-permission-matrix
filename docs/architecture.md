# Architektur – Berechtigungsmatrix

## Verantwortung

Die Berechtigungsmatrix ist eine eigenständige read-only App im Portfolio
IKT/Datenschutz. Sie inventarisiert und bewertet Berechtigungsquellen, ändert
aber weder Nextcloud-Rechte noch Konfigurationen anderer Apps.

## Quellen und Coverage

- Scanner, Matrixaufbau, Baseline/Diff, Export, Zugriffskontrolle und
  Persistenz bleiben getrennte Komponenten.
- App-eigene Berechtigungen werden ausschließlich über öffentliche
  Permission-Provider gelesen. Native Nextcloud-Rechte und optionale
  Fremd-App-Adapter bleiben getrennte Quellen.
- Fehlende, mehrdeutige oder inkompatible Quellen liefern `UNKNOWN`,
  `UNSUPPORTED` oder eine sichtbar partielle Coverage und niemals eine
  vermutete Freigabe.
- Die eng begrenzte Groupfolders-Ausnahme ist versionsgebunden und wird vor
  Releases gegen den offiziellen Quellstand geprüft.

## Datenschutz und Rechte

Standardscans und Exporte vermeiden Benutzerlisten, Secrets, Dateiinhalte und
personenbezogene Pfade. Viewer-, Administrations-, Baseline-, Scan- und
Exportrechte werden serverseitig geprüft. Native Administration allein
erteilt keinen fachlichen Vollzugriff.
