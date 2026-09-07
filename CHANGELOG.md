# Changelog

## Unreleased

- Organisationsconsumer auf `OCA\\LocalBase\\PublicApi\\V1` umgestellt und
  fehlende, ungültige sowie inkompatible Provider weiterhin fail-closed
  behandelt.
- Fresh Install und Runtime auf Nextcloud 34 mit und ohne LocalBase geprüft.
- Standalone-Testlauf von benachbarten Repositories entkoppelt, JavaScript-
  Tests für ESM-Hostprojekte als CommonJS gekennzeichnet und das
  Groupfolders-Quellgate um einen `grep`-Fallback für Images ohne `rg`
  ergänzt.
- Dokumentations- und Steuerungsstruktur vereinheitlicht.

## 0.2.2

- Bestehender Entwicklungsstand der Berechtigungsmatrix bei Einführung
  dieses Changelogs.
- Der aktuelle Funktions- und Coverage-Stand steht in `README.md`.
