# AGENTS.md - Filzmann Permission Matrix

## Projekt

Nextcloud-App `filzmann_permission_matrix` fuer eine dauerhaft einsehbare, exportierbare Berechtigungsmatrix der lokalen bzw. betrieblichen Nextcloud.

Lokale App-URL in der gemeinsamen DDEV-Umgebung:

    https://nextcloud-dev.ddev.site/apps/filzmann_permission_matrix/

Nextcloud-App-ID:

    filzmann_permission_matrix

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

- Dieses Verzeichnis ist ein eigenstaendiges Git-Repository fuer die Nextcloud-App `filzmann_permission_matrix`.
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
    ddev exec -d /var/www/html/html php occ app:list | grep -i filzmann_permission_matrix
    ddev exec -d /var/www/html/html php occ app:enable filzmann_permission_matrix
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

- Matrix, Snapshots und Exporte sind nur fuer konfigurierte Viewer-Gruppen, konfigurierte Admin-Gruppen oder Nextcloud-Admins sichtbar.
- `Betriebsrat`, `IKT-Ausschuss`, `Datenschutzbeauftragte` und
  `IT-Administration` sind anpassbare Ausgangsbeispiele, keine fest
  vorausgesetzten Organisationsrollen. Ein Personalrat kann die
  Betriebsratsgruppe vollständig ersetzen.
- App-Konfiguration, Baseline-Freigabe und manuelle Scans sind nur fuer Nextcloud-Admins oder konfigurierte Admin-Gruppen erlaubt.
- Alle App-Zugriffe werden mit Zeitpunkt, Benutzer-ID, Aktion, Export-Flag und optionaler Snapshot-ID in eigenen Audit-Tabellen protokolliert.
- Exportmetadaten und Auditprotokolle besitzen getrennt konfigurierbare
  REVIEW-Fristen mit jeweils 180 Tagen als Standard. Ein Fristablauf darf
  ohne gesondert freigegebenes Löschkonzept keine automatische Löschung
  auslösen.
- API-Aktionen mit Zustandsaenderung behalten CSRF-Schutz.
- Exporte enthalten standardmaessig keine Benutzerlisten, keine Share-Metadaten und nur redigierte Pfadangaben.
- Art.-15-Selbstauskunft wird optional und lazy über den öffentlichen
  V1-Vertrag von `filzmann_data_protection` registriert. Sie liest nur
  app-eigene Snapshot-Ersteller-, Export- und Auditbezüge der betroffenen UID.
  Snapshot-/Exportinhalte, freie Dateinamen, freie Auditdetails und Angaben
  anderer Personen bleiben ausgeschlossen. Fehlt oder ist die Privacy-App
  deaktiviert, muss die Permission-Matrix unverändert starten.

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

## Parent-Governance-Vertrag: 1

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
