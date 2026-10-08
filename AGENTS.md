# AGENTS.md - Filzmann Permission Matrix

## Projekt

Nextcloud-App `flz_permission_matrix` fuer eine dauerhaft einsehbare, exportierbare Berechtigungsmatrix der lokalen bzw. betrieblichen Nextcloud.

Lokale App-URL in der gemeinsamen DDEV-Umgebung:

    https://nextcloud-dev.ddev.site/apps/flz_permission_matrix/

Nextcloud-App-ID:

    flz_permission_matrix

## Zielsetzung

Die App stellt IKT, Datenschutz, Revision und berechtigten betrieblichen
Interessenvertretungen eine read-only Positivliste gruppenbezogener
Nextcloud-Berechtigungen bereit. Sie ist keine Gremien-Arbeits-App.

Kernprinzipien:

- Die App dokumentiert und bewertet Berechtigungen, veraendert sie aber nicht.
- Nicht auslesbare, unbekannte oder nicht adaptergestuetzte Berechtigungen duerfen nie als freigegeben erscheinen.
- Aktivierte Apps ohne Detailadapter werden als `UNSUPPORTED` bzw. im Strict Mode compliance-relevant markiert.
- Standardexporte enthalten keine Passwoerter, Tokens, privaten Schluessel, Dateiinhalte oder personenbezogenen Dateipfade.
- Personenbezogene Daten werden im Standardmodus vermieden; `include_users` bleibt standardmaessig `false`.

## Repository und gemeinsamer Arbeitsablauf

- Dieses Verzeichnis ist ein eigenstaendiges Git-Repository fuer die Nextcloud-App `flz_permission_matrix`.
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
    ddev exec -d /var/www/html/html php occ app:list | grep -i flz_permission_matrix
    ddev exec -d /var/www/html/html php occ app:enable flz_permission_matrix
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
- Die native App-Verfügbarkeit und die fachliche Detailabdeckung bleiben
  getrennt. Eine aktivierte App ohne Gruppenbeschränkung aus dem öffentlichen
  `OCP\App\IAppManager` gilt für jede erfasste Gruppe als verfügbar; eine
  Einschränkung gilt nur für die ausdrücklich genannten Gruppen. Fehlende
  Detailadapter verändern diesen Befund nicht und bleiben separat
  `UNSUPPORTED`.
- Nextcloud-eigene Apps, insbesondere Files und Calendar, werden als eigene
  priorisierte Rechtequellen behandelt. Adapter verwenden ausschließlich
  öffentliche OCP-Verträge und weisen deren Coverage-Grenzen aus; private
  DAV-Backends, DAV-Tabellen oder vermutete Defaults sind unzulässig.
- Ein nativer Nextcloud-Adminstatus ist keine automatische Fachfreigabe.
  Zeitlich begrenzter app-lokaler Vollzugriff wird im öffentlichen
  Permission-V1-Vertrag als UND aus `nextcloud-admin` und
  `app-admin-grant` beschrieben und niemals aus einer der beiden Bedingungen
  allein abgeleitet.
- Fehler werden zentral protokolliert; Logs enthalten keine Secrets, Dateiinhalte oder unnoetigen personenbezogenen Details.
- Keine Architekturabstraktion wird vorsorglich gebaut. Adapter wachsen anhand konkret auslesbarer Berechtigungsmodelle.
- Der Groupfolders-Adapter ist eine kontrollierte app-lokale Ausnahme für die
  fremde OCA-Runtime-API. Freigegeben ist ausschließlich die durch
  `scripts/check-groupfolders-source-compatibility` belegte 22.x-Struktur auf
  Nextcloud 34. Vor jedem Release mit diesem Adapter wird ein frischer
  offizieller Groupfolders-Checkout geprüft. Jede Versions-, DTO- oder
  Methodenabweichung blockiert die Freigabe und bleibt zur Laufzeit
  `UNKNOWN`, bis Adapter, Negativtests und Gate bewusst gemeinsam aktualisiert
  wurden.
- Der Groupfolders-Adapter übernimmt keine Mount-Pfade, Datei-/Ordnernamen
  oder Dateiinhalte. Erweiterte ACLs und nicht als Nextcloud-Gruppe
  abbildbare Team-/Circle-Zuordnungen verhindern eine Vollständigkeitsbehauptung.

## Zugriff und Sicherheit

- Matrix, Snapshots und Exporte sind nur fuer konfigurierte Viewer-Gruppen,
  konfigurierte Admin-Gruppen oder Nextcloud-Admins mit aktiver app-lokaler
  Vollzugriffsfreigabe sichtbar.
- `Betriebsrat`, `IKT-Ausschuss`, `Datenschutzbeauftragte` und
  `IT-Administration` sind anpassbare Ausgangsbeispiele, keine fest
  vorausgesetzten Organisationsrollen. Ein Personalrat kann die
  Betriebsratsgruppe vollständig ersetzen. Davon ausgenommen ist allein die
  app-lokale Adminfreigabe: deren Steuerungsrolle ist die kanonische native
  Gruppe `Datenschutzbeauftragte`.
- App-Konfiguration, Baseline-Freigabe und manuelle Scans sind nur fuer
  konfigurierte Admin-Gruppen oder Nextcloud-Admins mit aktiver app-lokaler
  Vollzugriffsfreigabe erlaubt. Die Freigabe wird je Admin erteilt, endet
  spaetestens nach 24 Stunden und bleibt mit Beginn, geplantem Ende und
  gegebenenfalls vorzeitigem Widerruf nachvollziehbar. Nur Mitglieder von
  `Datenschutzbeauftragte` duerfen die Historie lesen sowie Freigaben fuer
  aktuelle Nextcloud-Administrationskonten erteilen oder widerrufen;
  nativer Adminstatus allein reicht dafuer nicht.
- Alle App-Zugriffe werden mit Zeitpunkt, Benutzer-ID, Aktion, Export-Flag und optionaler Snapshot-ID in eigenen Audit-Tabellen protokolliert.
- Exportmetadaten und Auditprotokolle besitzen getrennt konfigurierbare
  REVIEW-Fristen mit jeweils 180 Tagen als Standard. Ein Fristablauf darf
  ohne gesondert freigegebenes Löschkonzept keine automatische Löschung
  auslösen.
- API-Aktionen mit Zustandsaenderung behalten CSRF-Schutz.
- Exporte enthalten standardmaessig keine Benutzerlisten, keine Share-Metadaten und nur redigierte Pfadangaben.
- Art.-15-Selbstauskunft wird optional und lazy über den öffentlichen
  V1-Vertrag von `flz_data_protection` registriert. Sie liest nur
  app-eigene Snapshot-Ersteller-, Export-, Audit- und
  Adminfreigabebezüge der betroffenen UID.
  Snapshot-/Exportinhalte, freie Dateinamen, freie Auditdetails und Angaben
  anderer Personen bleiben ausgeschlossen. Fehlt oder ist die Privacy-App
  deaktiviert, muss die Permission-Matrix unverändert starten.
- Der app-eigene statische Verarbeitungskatalog liegt ausschließlich in
  `resources/privacy-processing.json`, folgt dem Root-Vertrag
  `docs/contracts/privacy-processing-metadata.schema.json` und wird optional
  sowie lazy über den öffentlichen Processing-Metadata-V1-Vertrag des
  Datenschutz-Centers registriert. Er enthält keine personenbezogenen
  Laufzeitdaten; offene Fachentscheidungen bleiben
  `PRIVACY-DECISION-REQUIRED`.

## Tests

Schnelle lokale Einstiegspunkte:

    php tests/run.php
    node tests/run-js.mjs

Neue oder refaktorierte Fachlogik bekommt passende Tests fuer Scanner, Matrixaufbau, Diff und Exporte. Bei Controller-, DI-, Migrations-, Background-Job- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.

## Verbindliche Standalone-Navigation

- Die Berechtigungsmatrix registriert einen eigenen neutralen
  Nextcloud-Hauptnavigationseintrag und bleibt ohne OrgSuite erreichbar.
- Der Navigationslistener verwendet für die Sichtbarkeit denselben
  serverseitigen `AccessService` wie die geschützten App-Pfade.
- OrgSuite-Assets, BR-Suite-Menüs oder fremde Linklisten werden nicht geladen.
- Die bestehende serverseitige View-/Manage-Pruefung bleibt alleinige Autoritaet; Menuesichtbarkeit ist keine Berechtigung.

## Dokumentenverantwortung

- `README.md` beschreibt ausschließlich den aktuellen nutzbaren Stand,
  Installation, Betrieb, Tests und den Dokumentationsindex.
- `ROADMAP.md` enthält ausschließlich offene, zurückgestellte oder
  freigabepflichtige Arbeit und Entscheidungen.
- `CHANGELOG.md` dokumentiert erledigte Änderungen releasebezogen; erledigte
  Checklisten verbleiben nicht in der Roadmap.
- `docs/architecture.md` ist die ausführliche Quelle für geltende fachliche
  und technische Architekturverträge.
- `docs/manual-acceptance.md` enthält wiederholbare manuelle Prüfungen und
  keine Produktplanung.
- `AGENTS.md` enthält ausschließlich verbindliche Arbeits-, Sicherheits-,
  Architektur- und Prüfregeln. Zusätzliche Dokumente werden in `README.md`
  mit eindeutiger Zuständigkeit eingeordnet.

## Parent-Governance-Vertrag: 2

- Die für dieses Subrepository anwendbaren Regeln des Parent-Workspaces sind
  verbindlich. Dazu gehören insbesondere app-übergreifende ADRs und
  öffentliche Verträge, Repositorygrenzen sowie Workspace-, Delivery- und
  Release-Gates.
- Diese lokale `AGENTS.md` und die lokalen Skills bleiben die vollständige,
  ohne Parent-Checkout arbeitsfähige Repository-Steuerung. Die anwendbaren
  Parent-Regeln werden dafür hier oder in den lokalen Skills mitgeführt.
- Repository-lokale Regeln dürfen Parent-Verträge konkretisieren und verschärfen,
  aber nicht abschwächen oder umgehen.
- Bei einem Widerspruch gilt bis zur Klärung die strengere Regel. Die Arbeit
  stoppt, bis die kanonische Quelle bestimmt, die Regelprojektionen
  synchronisiert und eine erforderliche Entscheidung dokumentiert ist.
- Ist der Parent-Workspace nicht verfügbar, bleibt die lokale Steuerung
  wirksam. Vor Cross-App-, Release- oder Delivery-Arbeit muss ein vermuteter
  neuerer Parent-Stand oder eine Regelungslücke zuerst gegen den Parent
  geprüft werden.

### Entwicklungsphase und Kompatibilitätsbedarf

Entscheidung vom 5. September 2026: Das Gesamtprojekt befindet sich vollständig
in der Entwicklung. Es gibt kein PROD, keinen produktiven Datenbestand und
keinen bereits betriebenen Bestand mit zu erhaltendem Upgradepfad. STAGING
ist eine wegwerfbare Entwicklungs- und Integrationsumgebung und darf im
konkret beauftragten Reinstall vollständig neu aufgebaut werden. Wenige
externe Testnutzer ändern diese Einordnung nicht.

Vor einer Datenmigration, Legacy-Unterstützung, Compatibility Layer,
Deprecated API, Dual-Read/Dual-Write, einem Altschema-Fallback, Übergangsformat
oder der Unterstützung historischer Entwicklungsstände wird geprüft:

1. Wurde der betroffene Zustand jemals produktiv eingesetzt?
2. Benötigen reale Daten oder Nutzer seine Erhaltung?
3. Gibt es einen anderen konkreten technischen Erhaltungsgrund, insbesondere
   einen geltenden Plattform- oder externen API-Vertrag?

Sind alle relevanten Antworten nein, ist die saubere Breaking-Change-/
Reinstall-Lösung der Standard. Frühere rein interne Entwicklungsstände
begründen weder Abwärtskompatibilität noch eine Deprecationfrist.
Entwicklungsschemata dürfen durch ein kanonisches Installationsschema ersetzt,
alte interne APIs und Konfigurationsformate samt ausschließlich dafür
benötigten Adaptern und Tests entfernt werden. Architekturqualität und der
saubere Zielzustand haben Vorrang. Nextclouds nötige Installationsmigrationen
bleiben erhalten; ein Verzeichnisname `Migration` beweist keine Altlast.

Breaking Changes werden im selben Änderungskontext vollständig durchgezogen:
betroffene Provider, Consumer, standardisierte APIs, Vertragsversionen,
Metadaten, Tests und Dokumentation müssen zusammenpassen. Unterstützte
Nextcloud-/openDesk-Plattformverträge, externe Standards, Autorisierung und
Datenschutz gelten unverändert. Fehlende oder inkompatible optionale Provider
bleiben kontrolliert sichtbar. Ein Reinstall erlaubt keine privaten
Fremdtabellenzugriffe oder parallel erfundenen Plattformmechanismen.

Vor destruktiver Arbeit werden die tatsächlich benötigten externen
Testidentitäten, Gruppen, Rollen und nicht reproduzierbaren Testdaten gezielt
gesichert oder über bestehende native Setup-Strukturen reproduzierbar gemacht.
Echte Personen- und Zugangsdaten bleiben außerhalb von Git. Diese begrenzte
Sicherung begründet keine allgemeine Legacy-Unterstützung. Ein Reinstall
bleibt ein normaler unterstützter Entwicklungsweg; der vorhandene
Compatibility-Workflow besitzt den Fresh-Install-Nachweis, dessen aktueller
Belegstatus in `docs/workspace.md` beschrieben ist.

Diese Phase endet ausschließlich durch einen ausdrücklich dokumentierten,
von Simon freigegebenen **Production-Readiness-/Production-Freeze-Entscheid**.
Ein Release Candidate, eine Versionsnummer, ein Staging-Deployment oder ein
externer Testzugang lösen den Wechsel nicht aus. Der Entscheid wird in dieser
kanonischen Lifecycle-Quelle mit Datum, Geltungsbereich und betroffenem
Versions-/Datenstand festgehalten und in die lokale Steuerung projiziert.
Dann werden Upgradepfade, Datenbankmigrationen, Persistenz, Backup/Restore,
Rollback, Release-/API-Kompatibilitätszusagen, Deployment-/Freigabeprozess und
PROD→STAGING/COPY-Strategie neu bewertet. Eine vollständige PROD-Governance
wird jetzt nicht vorweggenommen.

Diese Regel entscheidet den Kompatibilitätsbedarf, erweitert aber keinen
Repository-Schreibauftrag und ersetzt keine Freigabe für eine konkrete
destruktive Aktion. Lokale Regelprojektionen folgen dem bestehenden
`docs/parent-governance-contract.md`; ein unsynchronisierter Einzel-Checkout
darf keinen abweichenden Phasenstand stillschweigend annehmen.

### Prüfaufwand

- Vor einem neuen Test, Scan, Linter, Architektur- oder Systemcheck wird
  geprüft, welcher bestehende Check dieselbe Eigenschaft bereits nachweist.
  Diesen erweitern oder sein nachweislich passendes Ergebnis wiederverwenden;
  ein zusätzlicher Check braucht eine benannte zusätzliche Fehlerklasse oder
  Vertrauensgrenze. Gleicher Input, gleiche Prüfung, gleiche Fehlerklasse und
  gleiche Phase begründen keinen zweiten Lauf. Gestaffelte Unit-, Contract-
  und Runtime-Nachweise bleiben erhalten. Die dokumentierten lokalen
  Prüfeinstiege bestimmen Umfang und Ergebnisgültigkeit.
