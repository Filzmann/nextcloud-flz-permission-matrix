# Berechtigungsmatrix

Nextcloud-App `filzmann_permission_matrix` fuer eine read-only Berechtigungsmatrix als laufend einsehbare Positivliste zu gruppenbezogenen Nextcloud-Berechtigungen.

## Zweck

Die App scannt aktivierte Apps, Gruppen, App-Gruppenbeschraenkungen und globale Files-/Sharing-/Admin-Policies. Optional liest sie konkrete native Nextcloud-Gruppenfreigaben mit ihren Datei- und Ordnerrechten. Sie erzeugt Snapshots, vergleicht diese gegen eine Baseline und exportiert die Matrix als JSON, CSV, Markdown oder HTML.

Nicht eindeutig auslesbare oder noch nicht adaptergestuetzte Berechtigungsmodelle werden als `UNKNOWN` oder `UNSUPPORTED` markiert und gelten nicht als freigegeben.

Die App-Verfügbarkeit ist davon getrennt: Liefert Nextcloud für eine
aktivierte App keine Gruppenbeschränkung, zeigt die Matrix diese App für jede
erfasste Gruppe als aktiviert an. Eine vorhandene Beschränkung aktiviert sie
nur für die darin genannten Gruppen. Dieser Befund beschreibt ausschließlich
den Zugang zur App; fehlende Detailadapter bleiben unabhängig davon
`UNSUPPORTED` und werden nicht zu vermuteten Fachrechten aufgewertet.

## Art.-15-Selbstauskunft

Ist die eigenständige App `filzmann_data_protection` kompatibel aktiviert,
registriert die Permission-Matrix lazy einen öffentlichen V1-Provider. Die
Auskunft ist serverseitig an die angemeldete Nextcloud-UID gebunden und
enthält ausschließlich eigene:

- Snapshot-Erstellungsbezüge mit neutralen Kennzahlen;
- Exportmetadaten ohne freien Dateinamen oder Exportinhalt;
- Auditaktionen ohne freie Auditdetails.

Snapshotinhalte, Benutzerlisten und Angaben anderer Personen werden nicht
übergeben. Die drei app-eigenen Tabellen werden read-only mit gebundenen
QueryBuilder-Parametern abgefragt. Opaque, providergebundene Cursor begrenzen
große Antworten. Fehlt oder ist das Datenschutz-Center deaktiviert, startet
die Permission-Matrix weiterhin ohne Auskunftsintegration.

Für Exportmetadaten und Auditprotokolle gelten getrennt konfigurierbare
Prüffristen von standardmäßig jeweils 180 Tagen. Die Art.-15-Auskunft weist
nach Fristablauf `REVIEW erforderlich` aus. Das ist bewusst nur ein
Prüfhinweis: Der aktuelle Stand löscht keine Datensätze automatisch.
Zusätzlich registriert die App lazy einen öffentlichen V1-Preview-Provider
beim Datenschutz-Center. Er meldet fällige technische Referenzen ohne UIDs,
freie Dateinamen, Auditdetails oder Inhalte. Der Vertrag besitzt keinen
Ausführungspfad.

## Gruppenfamilien

Die Matrix deutet Rollen und Bereiche ausschließlich über den validierten,
versionierten Organisationssnapshot von LocalBase. Dessen Vertragsversion,
Definitionsversion und Prüfsumme werden mit jedem Matrixsnapshot festgehalten.
Für die AdPlaner-Schemata `ad-ASN-<Kürzel>` und
`ad-ASN-<Kürzel>-Urlaub` stehen bei gültigem Organisationsvertrag zusätzlich
Team-, Familien- und vollständige Rohgruppenansichten zur Verfügung.

Die Zusammenfassung ersetzt weder in den gescannten Daten noch in Baselines, Diffs oder Exporten die Rohgruppen. Abweichende Rechte innerhalb einer Familie werden als Teilbelegung oder `gemischt` markiert; die zugehoerigen Rohwerte bleiben in der Ansicht nachvollziehbar. Nicht ausdruecklich erkannte Namensvarianten bleiben sichtbare Einzelgruppen.

Die Standardansicht sortiert Berechtigungen streng nach App. App-Abschnitte lassen sich einzeln oder gemeinsam ein- und ausklappen; ein Klick auf eine Gruppenueberschrift fokussiert diese Spalte. Diese Interaktionen filtern nur die read-only Darstellung und veraendern keine Nextcloud-Rechte.

Die Teamansicht ordnet bekannte technische Gruppen verständlich, zum Beispiel
`Team A1 · Assistenz`, während Rollen und Bereiche ihre fachlichen Labels aus
LocalBase erhalten. Die zugrunde liegenden Rohgruppen bleiben getrennt. Eine
N:N-Teamzuordnung entsteht erst durch die fachliche UND-Bedingung aus Team-
und Rollengruppe; sie wird nicht aus einem Gruppensuffix erfunden oder zu
einem scheinbaren Einzelrecht verschmolzen.

Fehlt LocalBase, ist sein Vertrag ungültig oder inkompatibel oder enthält er
mehrdeutige Gruppenzuordnungen, bleibt der Scan verfügbar. Die Matrix zeigt
dann jede Gruppe einzeln, markiert ihre fachliche Bedeutung als `UNKNOWN` und
speichert den konkreten Providerstatus im historischen Snapshot. Spätere
Konfigurationsänderungen deuten vorhandene Snapshots nicht neu.

## Installation lokal

Die App wird in der gemeinsamen DDEV-Nextcloud per Mount eingebunden:

```text
<WORKSPACE-ROOT>/filzmann_permission_matrix
-> /var/www/html/html/custom_apps/filzmann_permission_matrix
```

Aktivieren:

Aus dem dokumentierten `nextcloud-dev`-Root:

```bash
ddev exec -d /var/www/html/html php occ app:enable filzmann_permission_matrix
ddev exec -d /var/www/html/html php occ status
ddev exec -d /var/www/html/html php occ app:list | grep -i filzmann_permission_matrix
```

Die lokale Nextcloud 34-Umgebung hat keinen separaten `occ migrations:migrate`-Befehl. Migrationen laufen beim Aktivieren der App oder ueber `occ upgrade`, falls `occ status` einen DB-Upgrade-Bedarf meldet.

## Betrieb

Admin-Einstellungen:

- `viewer_groups`: frei anpassbare Gruppen mit Matrix-/Export-Zugriff. Als
  Ausgangsbeispiele dienen `Betriebsrat`, `IKT-Ausschuss`,
  `Datenschutzbeauftragte` und `IT-Administration`.
- `admin_groups`: frei anpassbare Gruppen mit Konfigurations-, Scan- und
  Baseline-Rechten; Ausgangsbeispiel ist `IT-Administration`.
- `scan_interval`: `hourly`, `daily` oder `weekly`.
- `baseline_snapshot`: aktuell genehmigter Positivlistenstand.
- `strict_mode`: neue/erweiterte Rechte werden strenger als nicht freigegeben bewertet.
- `include_users`: Standard `false`.
- `redact_paths`: Standard `true`.
- `include_share_metadata`: Standard `false`.
- `export_formats`: serverseitig erlaubte Exportformate aus `md,csv,json,html`.
- `retention`: Anzahl aufzubewahrender Snapshots.
- `export_metadata_retention_days`: Prüffrist für Exportmetadaten in Tagen,
  Standard `180`, zulässig `1` bis `3650`.
- `audit_retention_days`: Prüffrist für Auditprotokolle in Tagen, Standard
  `180`, zulässig `1` bis `3650`.

Beim Speichern werden Viewer-/Admin-Gruppen gegen die vorhandenen
Nextcloud-Gruppen geprüft. Ungültige Gruppen, Intervalle, Exportformate oder
Aufbewahrungswerte werden vollständig abgewiesen, bevor ein
Konfigurationsschlüssel geändert wird.

Im Normalfall sollte die zuständige Beschäftigtenvertretung die Matrix lesen
können. In Betrieben ist dies häufig der Betriebsrat, in Dienststellen der
Personalrat; die Beispielgruppe `Betriebsrat` kann vollständig durch die real
vorhandene Gruppe, etwa `Personalrat`, ersetzt werden. Ein IKT-Ausschuss und
Datenschutzbeauftragte benötigen typischerweise Leserechte für technische
Prüfung beziehungsweise datenschutzrechtliche Beratung, aber keine
Konfigurations-, Scan- oder Baseline-Rechte. Diese administrativen Rechte
sollten auf eine kleine zuständige Gruppe wie `IT-Administration` und native
Nextcloud-Administratoren begrenzt bleiben.

### Native Nextcloud-Gruppenfreigaben

Mit `include_share_metadata=true` liest die App Gruppenfreigaben ausschließlich
über `OCP\Share\IManager`. Pro Freigabe werden Lesen, Ändern, Erstellen,
Löschen und Teilen getrennt dargestellt. Nextcloud liefert für Shares kein
Ausführen-Recht; die Matrix weist es deshalb ausdrücklich als `n/a` aus.
Direkte Freigaben und Weiterfreigaben bleiben unterscheidbar.

Zur vollständigen Abfrage werden Owner-IDs aktiver und deaktivierter Konten
nur transient verwendet. Persistiert werden ausschließlich Zielgruppe,
empfängerrelativer Zielpfad, Datei-/Ordnertyp, Permission-Maske, ein gehashter
Share-Verweis und die Information direkte Freigabe/Weiterfreigabe. Dateiinhalte,
Owner-IDs, Share-Tokens und Credentials werden weder gespeichert noch
exportiert. Die Daten liegen nur in den eigenen Matrix-Snapshots und unterliegen
deren konfigurierter `retention`.

Konkrete Pfade sind in der geschützten Matrix sichtbar. Bei
`redact_paths=true` ersetzt jeder Export sie und pfadbezogene Diffangaben durch
exportlokale neutrale Platzhalter. Ohne `include_share_metadata` findet keine
Einzelfreigaben-Abfrage statt.

### Team Folders / Groupfolders

Aktive Team Folders werden über einen eigenen read-only Adapter analysiert.
Der freigegebene Vertrag gilt derzeit ausschließlich für die offizielle
Groupfolders-Version 22.x auf Nextcloud 34. Er verwendet
`FolderManager::getAllFolders()` und übernimmt nur pseudonyme Ordnerreferenzen,
Nextcloud-Gruppen-IDs, Root-Permission-Masken und das Vorhandensein erweiterter
ACLs. Mount-Pfade, Datei- und Ordnernamen sowie Dateiinhalte werden weder in
die Matrix noch in Snapshots oder Exporte übernommen.

Root-Rechte werden getrennt als R/W/C/D/S dargestellt; Ausführen ist `n/a`.
Sind erweiterte ACLs aktiv, existieren Team-/Circle-Zuordnungen, ist die App-
Version nicht freigegeben oder weicht der fremde Quellvertrag ab, bleiben die
beobachtbaren Root-Werte sichtbar, die betroffenen Zeilen und der Adapterstatus
werden aber fail-closed als `UNKNOWN` beziehungsweise `PARTIAL` markiert. Die
historische App-ID `files_groupfolders` wird sichtbar erkannt, ohne eine
Kompatibilität mit der heutigen Runtime-API zu unterstellen.

### Native Files- und Calendar-Berechtigungen

Files wird aus mehreren getrennten Quellen zusammengesetzt. Native
Gruppenfreigaben und die freigegebene Groupfolders-Projektion sind bereits
auslesbar. Files Access Control und externe Speicher bleiben unvollständig und
werden nicht aus internen Tabellen oder Klassen rekonstruiert. Dateiinhalte
bleiben ausgeschlossen; für Pfade und Share-Metadaten gelten weiterhin die
Opt-in- und Redaktionsregeln.

Für Calendar ist die App-Verfügbarkeit bereits sichtbar. Die öffentlichen
`OCP\Calendar\IManager`- und `OCP\Calendar\ICalendar`-Schnittstellen können
effektive Rechte für einen bestimmten Principal liefern, stellen aber allein
keine vollständige globale Gruppenfreigaben-Inventur bereit. Bis Scan-Scope
und Vollständigkeitsgrenze festgelegt sind, bleiben Calendar-Details deshalb
`UNSUPPORTED`. Kalender- und Termininhalte werden nicht gelesen; private
DAV-Backends und DAV-Tabellen dienen nicht als Fallback.

### App-eigene Berechtigungen

App-eigene Detailrechte werden nur über einen öffentlichen, versionierten und
read-only Providervertrag der zuständigen App übernommen. Interne Services,
Tabellen oder private Konfiguration anderer Apps werden nicht gelesen und ihre
Regeln werden nicht in der Matrix nachgebaut. Fehlt ein solcher Vertrag, bleibt
die native Nextcloud-App-Gruppeneinschränkung sichtbar; die Detailabdeckung wird
kontrolliert als `UNSUPPORTED` ausgewiesen.

Der öffentliche Vertrag liegt unter
`OCA\FilzmannPermissionMatrix\PublicApi\V1`. Provider registrieren sich lazy am
`RegisterPermissionProvidersEvent` und liefern ausschließlich Regeln aus ihrer
eigenen kanonischen Berechtigungslogik. V1 unterscheidet Gruppenbedingungen
(`group`, `all`, `any`) ausdrücklich von `self`, `authenticated` und
`nextcloud-admin`. Nicht gruppenbezogene Bedingungen werden in Gruppenzellen als
`n/a`, aber in der maschinenlesbaren Zugriffsregel vollständig ausgewiesen.
Gekoppelte Gruppenbedingungen erscheinen als `AND`, nicht als mehrere
unabhängige Freigaben. Details und ein Integrationsbeispiel stehen in
[`docs/permission-provider-v1.md`](docs/permission-provider-v1.md).

Die oben dokumentierte, versions- und releasegebundene Groupfolders-Quelle ist
die einzige eng begrenzte Ausnahme von diesem öffentlichen Providerprinzip.

Die Berechtigungsmatrix selbst liest ihre Viewer- und Admin-Gruppen direkt aus
derselben `ConfigService`-Konfiguration, die der serverseitige `AccessService`
erzwingt. Verwaltung umfasst Lesen; reine Viewer erhalten kein Verwaltungsrecht.

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
- Native Sharing-Policies aus öffentlichen Nextcloud-APIs und optionale
  konkrete Gruppenfreigaben mit getrennten R/W/C/D/S-Rechten.
- Matrixansicht mit Filtern.
- Semantische, per Pfeiltasten bedienbare Tabs und vollständiger Filter-Reset.
- Versionierter LocalBase-Organisationssnapshot mit kontrolliertem
  `UNKNOWN`-Fallback.
- Snapshot-Speicherung, Baseline und Diff-Grundlagen.
- Background Job.
- `occ`-Kommandos.
- JSON/CSV/Markdown/HTML-Export.

Geplante Adapter und weitere Exportformate stehen in der
[Roadmap](ROADMAP.md).

## Tests

Schnelle lokale Checks:

```bash
php tests/run.php
node tests/run-js.mjs
```

Vor jedem Release, der den Groupfolders-Adapter enthält, muss zusätzlich ein
frischer offizieller `stable34`-Checkout geprüft werden:

```bash
scripts/check-groupfolders-source-compatibility /path/to/nextcloud-groupfolders
```

Der Check blockiert bei einem anderen Groupfolders-Major, einer anderen
Nextcloud-Zielversion oder Änderungen an den verwendeten DTOs und Methoden.
Eine Anpassung des Gates ohne gleichzeitige fachliche Prüfung und Tests des
Adapters ist unzulässig.

Bei Controller-, DI-, Migration-, Background-Job- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.

Für die fachliche, visuelle und sicherheitsbezogene Staging-Prüfung steht ein
ausfüllbares [manuelles Abnahmeformular](docs/manual-acceptance.md) bereit.
Belege werden darin ausschließlich synthetisch beziehungsweise redigiert
dokumentiert.
