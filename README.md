# Berechtigungsmatrix

Nextcloud-App `br_permission_matrix` fuer eine read-only Berechtigungsmatrix als laufend einsehbare Positivliste zu gruppenbezogenen Nextcloud-Berechtigungen.

## Zweck

Die App scannt aktivierte Apps, Gruppen, App-Gruppenbeschraenkungen und erste globale Files-/Sharing-/Admin-Policies. Sie erzeugt Snapshots, vergleicht diese gegen eine Baseline und exportiert die Matrix als JSON, CSV, Markdown oder HTML.

Nicht eindeutig auslesbare oder noch nicht adaptergestuetzte Berechtigungsmodelle werden als `UNKNOWN` oder `UNSUPPORTED` markiert und gelten nicht als freigegeben.

## Gruppenfamilien

Die Matrix kann bekannte, massenhaft auftretende technische Gruppen zu fachlichen Familien zusammenfassen. Fuer die AdPlaner-Schemata `ad-ASN-<Kuerzel>`, `ad-ASN-<Kuerzel>-Urlaub`, `ad-EB-*` und `ad-PFK-*` stehen Team-, Familien- und vollstaendige Rohgruppenansicht zur Verfuegung.

Die Zusammenfassung ersetzt weder in den gescannten Daten noch in Baselines, Diffs oder Exporten die Rohgruppen. Abweichende Rechte innerhalb einer Familie werden als Teilbelegung oder `gemischt` markiert; die zugehoerigen Rohwerte bleiben in der Ansicht nachvollziehbar. Nicht ausdruecklich erkannte Namensvarianten bleiben sichtbare Einzelgruppen.

Die Standardansicht sortiert Berechtigungen streng nach App. App-Abschnitte lassen sich einzeln oder gemeinsam ein- und ausklappen; ein Klick auf eine Gruppenueberschrift fokussiert diese Spalte. Diese Interaktionen filtern nur die read-only Darstellung und veraendern keine Nextcloud-Rechte.

Die Teamansicht ordnet bekannte technische Gruppen verstaendlich, zum Beispiel `Team A1 · Assistenz`, `Rolle EB · Koordination` oder `Rolle PFK · Pflege`. Die zugrunde liegenden `ad-ASN-*`, `ad-EB-*`, `ad-PFK-*` und Urlaubsgruppen bleiben getrennt. Eine N:N-Teamzuordnung entsteht erst durch die fachliche UND-Bedingung aus Team- und Rollengruppe; sie wird nicht aus einem Gruppensuffix erfunden oder zu einem scheinbaren Einzelrecht verschmolzen.

## Installation lokal

Die App wird in der gemeinsamen DDEV-Nextcloud per Mount eingebunden:

```text
<WORKSPACE-ROOT>/br_permission_matrix
-> /var/www/html/html/custom_apps/br_permission_matrix
```

Aktivieren:

Aus dem dokumentierten `nextcloud-dev`-Root:

```bash
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
- `export_formats`: serverseitig erlaubte Exportformate aus `md,csv,json,html`.
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

Geplante Adapter, weitere Exportformate und die Prüfung des gemeinsamen
AD-Gruppenvertrags stehen in der [Roadmap](ROADMAP.md).

## Tests

Schnelle lokale Checks:

```bash
php tests/run.php
node tests/run-js.mjs
```

Bei Controller-, DI-, Migration-, Background-Job- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.
