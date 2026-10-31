# Changelog

## Unreleased

- Organisationsidentität und App-Rechte sind getrennt: Die kanonische
  BR-Mitgliedergruppe aus dem öffentlichen LocalBase-V1-Vertrag erhält
  unveränderlich Matrix-, Export- und manuelle Scanrechte;
  `Datenschutzbeauftragte` erhalten zusätzlich Baseline- und
  Konfigurationsrechte. Fehlende, deaktivierte, inkompatible oder ungültige
  Providerstände erteilen fail-closed keine BR-Rechte.
- Die technische App-Konfiguration ist für Datenschutzbeauftragte im eigenen
  semantischen Einstellungstab erreichbar; der native Adminbereich verwendet
  dasselbe Formular und keine zweite Konfigurationsquelle.
- Für breite Matrizen bleibt der synchronisierte horizontale Scroll-Track im
  sichtbaren Matrix-Viewport fokussierbar; das Abnahmeformular beschreibt die
  Prüfung kleiner Viewports, Tastaturfokus sowie kombiniertes Scrollen.
- Technische App-Identität auf `flz_permission_matrix` und
  `OCA\\FlzPermissionMatrix` umgestellt. App-eigene Tabellen und Indizes
  verwenden für den vereinbarten Fresh-Install-Neustart das Präfix `flz_pm`;
  die sichtbare Filzmann-Produktbezeichnung bleibt erhalten.
- Temporäre fachliche Adminfreigaben aus dem technischen Adminbereich in den
  rollenabhängigen App-Einstieg verschoben. Nur Mitglieder von
  `Datenschutzbeauftragte` können die Historie lesen und für aktuelle native
  Administrationskonten höchstens 24 Stunden gültige Freigaben erteilen oder
  widerrufen; native Administration allein bleibt ohne Steuerungsrecht.
- DPO-Freigaberechte, Audit-/Art.-15-Projektion und Processing-Metadaten an
  die serverseitig getestete Allow-, Deny- und Manipulationspolicy angepasst.
- App-eigenen Processing-Metadata-Katalog für Snapshot/Matrix, Export, Audit
  und temporäre Adminfreigaben über den optionalen V1-Vertrag des
  Datenschutz-Centers veröffentlicht.
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
