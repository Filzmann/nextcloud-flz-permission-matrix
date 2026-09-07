# Changelog

## Unreleased

- Organisationsconsumer auf `OCA\\LocalBase\\PublicApi\\V1` umgestellt und
  fehlende, ungültige sowie inkompatible Provider weiterhin fail-closed
  behandelt.
- Die optionale Providerklasse wird erst nach Aktivierungs- und Klassenprüfung
  über `OCP\\Server` aufgelöst. Damit bleibt auch ein aktivierter älterer
  LocalBase-Stand ohne V1-Service kontrolliert inkompatibel.
- Fresh Install und Runtime auf Nextcloud 34 mit und ohne LocalBase geprüft.
- Deaktivierung, Entfernung und Wiederinstallation des aktuellen V1-Providers
  sowie eines älteren LocalBase-Stands ohne V1 auf Nextcloud 34 geprüft.
- In-place-Update von LocalBase `0.12.0-dev.1` auf `0.12.0-dev.2` mit
  unverändertem synthetischem Organisationszustand geprüft.
- Standalone-Testlauf von benachbarten Repositories entkoppelt, JavaScript-
  Tests für ESM-Hostprojekte als CommonJS gekennzeichnet und das
  Groupfolders-Quellgate um einen `grep`-Fallback für Images ohne `rg`
  ergänzt.
- Dokumentations- und Steuerungsstruktur vereinheitlicht.

## 0.2.2

- Bestehender Entwicklungsstand der Berechtigungsmatrix bei Einführung
  dieses Changelogs.
- Der aktuelle Funktions- und Coverage-Stand steht in `README.md`.
