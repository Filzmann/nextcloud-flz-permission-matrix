# AGENTS.md - BR Permission Matrix

## Projekt

Nextcloud-App `br_permission_matrix` fuer eine dauerhaft einsehbare, exportierbare Berechtigungsmatrix der lokalen bzw. betrieblichen Nextcloud.

Lokale App-URL in der gemeinsamen DDEV-Umgebung:

    https://nextcloud-dev.ddev.site/apps/br_permission_matrix/

Nextcloud-App-ID:

    br_permission_matrix

## Zielsetzung

Die App stellt Betriebsrat, IKT-Ausschuss, Datenschutz und IT-Administration eine read-only Positivliste aller gruppenbezogenen Nextcloud-Berechtigungen bereit.

Kernprinzipien:

- Die App dokumentiert und bewertet Berechtigungen, veraendert sie aber nicht.
- Nicht auslesbare, unbekannte oder nicht adaptergestuetzte Berechtigungen duerfen nie als freigegeben erscheinen.
- Aktivierte Apps ohne Detailadapter werden als `UNSUPPORTED` bzw. im Strict Mode compliance-relevant markiert.
- Standardexporte enthalten keine Passwoerter, Tokens, privaten Schluessel, Dateiinhalte oder personenbezogenen Dateipfade.
- Personenbezogene Daten werden im Standardmodus vermieden; `include_users` bleibt standardmaessig `false`.

## Repository und gemeinsamer Arbeitsablauf

- Dieses Verzeichnis ist ein eigenstaendiges Git-Repository fuer die Nextcloud-App `br_permission_matrix`.
- Andere Apps und der Parent-Workspace werden nicht aus diesem Repo heraus gestaged oder committed.
- Diese Datei und lokal referenzierte Skills bilden bei einem direkten Start in diesem Repository die vollständige Repository-Steuerung.
- Fuer Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgefuehrte Skill `work-in-nextcloud-app`; die folgenden Matrix-Regeln und Pruefungen ergaenzen ihn.

## DDEV

Die gemeinsame Nextcloud-DDEV-Umgebung wird aus dem dokumentierten
Parent-Unterverzeichnis `nextcloud-dev` gesteuert. Bei einem eigenständigen
Checkout ist der lokale DDEV-Pfad zuerst anhand der realen Umgebung zu
ermitteln.

Wichtige Pruefungen:

    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i br_permission_matrix
    ddev exec -d /var/www/html/html php occ app:enable br_permission_matrix
    ddev exec -d /var/www/html/html php occ permission-matrix:status
    ddev exec -d /var/www/html/html php occ permission-matrix:scan

## Architekturregeln

- Controller bleiben duenn.
- Scanner, Matrixaufbau, Baseline/Diff, Exporte, Zugriffskontrolle und Datenzugriff bleiben getrennte Services bzw. Stores.
- Adapter lesen nur Daten und schreiben keine Nextcloud-Berechtigungen oder Fremd-App-Konfigurationen.
- Datenzugriffe laufen ueber Mapper-/Repository-Klassen und bleiben auf eigene Tabellen beschraenkt.
- Services arbeiten bevorzugt mit kleinen DTOs/Value Objects statt unstrukturierten Arrays, sobald Daten mehrere Schichten durchlaufen.
- Modelle/DTOs verwenden `get(...)` fuer Einzelobjekte, `get_all([...])` fuer Listen, `toArray()` fuer Serialisierung und kein `save()`, sofern sie nicht selbst persistieren.
- Fehlende Tabellen, deaktivierte Apps oder nicht eindeutige Rechtequellen fuehren zu `UNKNOWN` oder `UNSUPPORTED`, nicht zu stillschweigender Freigabe.
- Fehler werden zentral protokolliert; Logs enthalten keine Secrets, Dateiinhalte oder unnoetigen personenbezogenen Details.
- Keine Architekturabstraktion wird vorsorglich gebaut. Adapter wachsen anhand konkret auslesbarer Berechtigungsmodelle.

## Zugriff und Sicherheit

- Matrix, Snapshots und Exporte sind nur fuer konfigurierte Viewer-Gruppen, konfigurierte Admin-Gruppen oder Nextcloud-Admins sichtbar.
- App-Konfiguration, Baseline-Freigabe und manuelle Scans sind nur fuer Nextcloud-Admins oder konfigurierte Admin-Gruppen erlaubt.
- Alle App-Zugriffe werden mit Zeitpunkt, Benutzer-ID, Aktion, Export-Flag und optionaler Snapshot-ID in eigenen Audit-Tabellen protokolliert.
- API-Aktionen mit Zustandsaenderung behalten CSRF-Schutz.
- Exporte enthalten standardmaessig keine Benutzerlisten, keine Share-Metadaten und nur redigierte Pfadangaben.

## Tests

Schnelle lokale Einstiegspunkte:

    php tests/run.php
    node tests/run-js.mjs

Neue oder refaktorierte Fachlogik bekommt passende Tests fuer Scanner, Matrixaufbau, Diff und Exporte. Bei Controller-, DI-, Migrations-, Background-Job- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.

## Verbindliche Suite-Navigation

- Die Berechtigungsmatrix registriert keinen eigenen Nextcloud-Hauptnavigationseintrag. `orgsuite` stellt den gemeinsamen Einstieg `BR` bereit.
- Das Template bindet das zentrale OrgSuite-Menue mit `data-suite="br"` und `data-current-app="br_permission_matrix"` ein.
- Die bestehende serverseitige View-/Manage-Pruefung bleibt alleinige Autoritaet; Menuesichtbarkeit ist keine Berechtigung.
