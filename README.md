# Berechtigungsmatrix

Nextcloud-App `br_permission_matrix` fuer eine read-only Berechtigungsmatrix als laufend einsehbare Positivliste zu gruppenbezogenen Nextcloud-Berechtigungen.

## Zweck

Die App scannt aktivierte Apps, Gruppen, App-Gruppenbeschraenkungen und erste globale Files-/Sharing-/Admin-Policies. Sie erzeugt Snapshots, vergleicht diese gegen eine Baseline und exportiert die Matrix als JSON, CSV, Markdown oder HTML.

Nicht eindeutig auslesbare oder noch nicht adaptergestuetzte Berechtigungsmodelle werden als `UNKNOWN` oder `UNSUPPORTED` markiert und gelten nicht als freigegeben.

## Gruppenfamilien

Die Matrix fasst bekannte, massenhaft auftretende technische Gruppen standardmaessig zu fachlichen Familien zusammen. Aktuell werden die AdPlaner-Schemata `ad-ASN-<Kuerzel>`, `ad-ASN-<Kuerzel>-Urlaub` und `ad-EB-*` getrennt dargestellt. Die Matrix kann jederzeit auf die vollstaendige Rohgruppenansicht umgeschaltet werden.

Die Zusammenfassung ersetzt weder in den gescannten Daten noch in Baselines, Diffs oder Exporten die Rohgruppen. Abweichende Rechte innerhalb einer Familie werden als Teilbelegung oder `gemischt` markiert; die zugehoerigen Rohwerte bleiben in der Ansicht nachvollziehbar. Nicht ausdruecklich erkannte Namensvarianten bleiben sichtbare Einzelgruppen.

## Installation lokal

Die App wird in der gemeinsamen DDEV-Nextcloud per Mount eingebunden:

```text
~/projects/br-nextcloud-apps/br_permission_matrix
-> /var/www/html/html/custom_apps/br_permission_matrix
```

Aktivieren:

```bash
cd ~/projects/br-nextcloud-apps/nextcloud-dev
ddev exec -d /var/www/html/html php occ app:enable br_permission_matrix
ddev exec -d /var/www/html/html php occ status
ddev exec -d /var/www/html/html php occ app:list | grep -i br_permission_matrix
```

Die lokale Nextcloud 34-Umgebung hat keinen separaten `occ migrations:migrate`-Befehl. Migrationen laufen beim Aktivieren der App oder ueber `occ upgrade`, falls `occ status` einen DB-Upgrade-Bedarf meldet.

## Betrieb

Admin-Einstellungen:

- `viewer_groups`: Gruppen mit Matrix-/Export-Zugriff.
- `admin_groups`: Gruppen mit Konfigurations-, Scan- und Baseline-Rechten.
- `scan_interval`: `hourly`, `daily` oder `weekly`.
- `baseline_snapshot`: aktuell genehmigter Positivlistenstand.
- `strict_mode`: neue/erweiterte Rechte werden strenger als nicht freigegeben bewertet.
- `include_users`: Standard `false`.
- `redact_paths`: Standard `true`.
- `include_share_metadata`: Standard `false`.
- `export_formats`: `md,csv,json,html` sowie spaeter optional `xlsx,pdf`.
- `retention`: Anzahl aufzubewahrender Snapshots.

`occ`-Kommandos:

```bash
php occ permission-matrix:scan
php occ permission-matrix:export --format=md --output=/tmp/matrix.md
php occ permission-matrix:export --format=csv --output=/tmp/matrix.csv
php occ permission-matrix:baseline:set <snapshot-id>
php occ permission-matrix:baseline:show
php occ permission-matrix:diff <snapshot-a> <snapshot-b>
php occ permission-matrix:status
```

## Sicherheit

- Die App veraendert keine Nextcloud-Berechtigungen.
- Die App schreibt nur in eigene Tabellen mit Prefix `permission_matrix_*`.
- Exporte enthalten standardmaessig keine Dateiinhalte, Passwoerter, Tokens, privaten Schluessel, Benutzerlisten oder personenbezogenen Dateipfade.
- Unbekannte oder nicht unterstuetzte Rechtequellen werden nicht als erlaubt dargestellt.
- Matrix, Snapshots und Exporte sind auf konfigurierte Viewer-/Admin-Gruppen bzw. Nextcloud-Admins beschraenkt.
- Konfiguration, Baseline-Freigabe und manuelle Scans sind auf Nextcloud-Admins bzw. konfigurierte Admin-Gruppen beschraenkt.
- App-Zugriffe werden in der eigenen Audit-Tabelle protokolliert, ohne sensible Inhalte zu loggen.

## Entwicklungsstand

MVP:

- App-Skeleton mit dynamischer Navigation.
- Admin-Konfigurationsseite.
- Read-only Scan von Gruppen, aktivierten Apps und App-Gruppenbeschraenkungen.
- Matrixansicht mit Filtern.
- Snapshot-Speicherung, Baseline und Diff-Grundlagen.
- Background Job.
- `occ`-Kommandos.
- JSON/CSV/Markdown/HTML-Export.

Noch bewusst konservativ bzw. als Klaerung markiert:

- Konkrete Team-/Group-Folder-Rechte.
- Files-Access-Control-Regelgruppen.
- App-spezifische Detailrechte fuer Deck, Collectives, Tables, Talk, Calendar, Contacts, Forms, Notes, Richdocuments, OnlyOffice, LDAP und Two-Factor.
- XLSX/PDF-Export.

## Tests

Schnelle lokale Checks:

```bash
php tests/run.php
node tests/run-js.mjs
```

Bei Controller-, DI-, Migration-, Background-Job- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.
