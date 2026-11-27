# Changelog

## Unreleased

- L10N-Migration (ZM-06): sichtbare Texte über Nextcloud-l10n (deutsche Quelltexte, Kataloge `l10n/en.*` + `l10n/en_GB.*`), inkl. `tests/Ui/L10nCatalogContractTest.php` und `docs/l10n-inventory.md`.

- Organisationsproviderfehler werden mit ihrer technischen Exceptionklasse
  protokolliert; Exceptionobjekt und interne Details gelangen nicht ins Log.
  Der bestehende Verhaltenstest wurde dafür zuerst rot und nach der Korrektur
  grün ausgeführt.
- Der Adminzugriffs-Smoke ordnet seine VM-Ausführung über den absoluten Pfad
  der gelesenen JavaScript-Datei der Coverage zu.
- Organisationsidentität und App-Rechte sind getrennt: Die kanonische
  BR-Mitgliedergruppe aus dem öffentlichen
  `OCA\\FlzOrganization\\PublicApi\\V1`-Organisationssnapshot erhält
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
- Organisationsconsumer vom bisherigen LocalBase-Vertrag auf den öffentlichen
  `OCA\\FlzOrganization\\PublicApi\\V1`-Vertrag umgestellt. Semantische Rollen,
  Bereiche und die kanonische BR-Mitgliedergruppe stammen damit aus demselben
  validierten Snapshot.
- Die optionale Providerklasse wird erst nach Aktivierungs-, Versions- und
  Klassenprüfung über `OCP\\Server` aufgelöst. Fehlende, deaktivierte,
  inkompatible, ungültige und nicht verfügbare Providerzustände bleiben
  kontrolliert und erteilen keine BR-Rechte.
- Standalone-Testlauf von benachbarten Repositories entkoppelt, JavaScript-
  Tests für ESM-Hostprojekte als CommonJS gekennzeichnet und das
  Groupfolders-Quellgate um einen `grep`-Fallback für Images ohne `rg`
  ergänzt.
- Dokumentations- und Steuerungsstruktur vereinheitlicht.

## 0.2.2

- Bestehender Entwicklungsstand der Berechtigungsmatrix bei Einführung
  dieses Changelogs.
- Der aktuelle Funktions- und Coverage-Stand steht in `README.md`.
