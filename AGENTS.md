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

## Git- und Arbeitsregeln

- Dieses Verzeichnis ist ein eigenstaendiges Git-Repository fuer die Nextcloud-App `br_permission_matrix`.
- Andere Apps und der Parent-Workspace werden nicht aus diesem Repo heraus gestaged oder committed.
- Keine Commits, kein Push und kein Deployment ohne ausdrueckliche Freigabe durch Simon.
- Vor Commits immer `git status --short`, `git diff --stat` und `git diff --name-only` zeigen.
- Nicht `git add .` verwenden; Dateien gezielt stagen.
- Aenderungen klein, pruefbar und rueckbaubar halten.

## DDEV

Die gemeinsame lokale Nextcloud-DDEV-Umgebung liegt ausserhalb dieses Repos:

    ~/projects/br-nextcloud-apps/nextcloud-dev

Wichtige Pruefungen:

    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i br_permission_matrix
    ddev exec -d /var/www/html/html php occ app:enable br_permission_matrix
    ddev exec -d /var/www/html/html php occ permission-matrix:status
    ddev exec -d /var/www/html/html php occ permission-matrix:scan

Diese lokale Nextcloud-Version hat keinen `occ migrations:migrate`-Befehl. App-Migrationen laufen beim Aktivieren der App bzw. ueber `occ upgrade`, wenn Nextcloud einen DB-Upgrade-Bedarf meldet.

In Codex-Sessions koennen DDEV-Befehle im normalen Sandbox-Kontext nicht zuverlaessig auf Docker zugreifen. Wenn `ddev` mit Docker-/Stream-FD-Fehlern scheitert, den gleichen Befehl mit eskaliertem Zugriff erneut ausfuehren.

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

## Learnings pflegen

### Gemeinsame Suite-Navigation

- Die Berechtigungsmatrix registriert keinen eigenen Nextcloud-Hauptnavigationseintrag. `orgsuite` stellt den gemeinsamen Einstieg `BR` bereit.
- Das Template bindet das zentrale OrgSuite-Menue mit `data-suite="br"` und `data-current-app="br_permission_matrix"` ein.
- Die bestehende serverseitige View-/Manage-Pruefung bleibt alleinige Autoritaet; Menuesichtbarkeit ist keine Berechtigung.

- App-spezifische Learnings werden in dieser `AGENTS.md` gespeichert.
- App-uebergreifende Learnings werden im Parent-Workspace dokumentiert und bei Bedarf hier wiederholt.
- Ergaenzungen erfolgen erst nach ausdruecklicher Freigabe.
