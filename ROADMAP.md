# Roadmap – Berechtigungsmatrix

Diese Datei enthält die offenen Adapter, Exportziele und Produktentscheidungen.
Bereits umgesetzte Grundlagen werden nur als Abgrenzung der verbleibenden
Arbeit genannt. Der geltende read-only Sicherheitsvertrag steht in
`AGENTS.md`; der aktuelle Funktionsumfang steht in `README.md`.

Die Berechtigungsmatrix steht noch am Anfang ihrer Entwicklung. Die folgenden
Aufgaben legen deshalb fachliche Zielverträge und Sicherheitsgrenzen fest,
ohne das heutige Matrixmodell vorschnell als endgültige Struktur zu behandeln.

## Nextcloud-Kompatibilitätsgate

### BPM-NC-COMPAT – Core-Bereich 29–35 und optionale Adapter getrennt belegen

`info.xml` umfasst Nextcloud 33 bereits. Vor dem nächsten Release werden alle
deklarierten Majors lückenlos mit Fresh Install/Upgrade, DI, Provider-Scan,
Permission-V1-Vertrag, Assets und sichtbarer Matrix geprüft. Die private,
eng begrenzte Groupfolders-Ausnahme bleibt davon getrennt: auf nicht
nachgewiesenen Groupfolders-/Nextcloud-Kombinationen liefert sie
`UNKNOWN`/`UNSUPPORTED`, ohne den Core-Betrieb als vollständig zu behaupten.
Eine höhere `max-version` folgt nur aus dem app-lokalen
`verify-nextcloud-future-compatibility`-Nachweis.

## Freigegebene Umsetzungsaufgaben

### BPM-CUSTOM-APP-PERMISSIONS – Berechtigungen aus der zuständigen App lesen

Status: öffentlicher V1-Provider-/Consumervertrag umgesetzt; die Provider der
sechs AD-Fachapps, beider BR-Apps und von `filzmann_data_protection` sind im
Parent gemeinsam gegen die echten V1-Klassen geprüft

- Jede Custom App mit eigener Berechtigungssteuerung stellt ihr vollständiges
  Berechtigungsmodell über eine stabile, direkt und read-only auslesbare
  öffentliche Schnittstelle bereit.
- Die Berechtigungsmatrix ruft diese Detailberechtigungen ausschließlich bei
  der jeweils zuständigen App ab. Sie liest dafür weder deren Tabellen,
  Entitäten, interne Services oder Konfiguration direkt noch führt sie eine
  zweite, unabhängig gepflegte Abbildung der App-Berechtigungen.
- Die Custom App bleibt die kanonische Quelle und liefert die fachliche
  Bedeutung sowie die effektiv geltenden Rechte, Scopes und Einschränkungen.
  Nicht eindeutig gelieferte Zustände werden als `UNKNOWN` oder `UNSUPPORTED`
  ausgewiesen und niemals als erlaubt ergänzt.
- Besitzt eine App keine eigene Berechtigungssteuerung, bewertet die Matrix
  ausschließlich die native Nextcloud-Gruppeneinschränkung der App. Mit einer
  solchen Einschränkung ist die App nur für die dort eingetragenen Gruppen
  verfügbar; ohne Einschränkung steht die App vollständig allen
  Nextcloud-Benutzern zur Verfügung und wird für alle erfassten Gruppen als
  nutzbar ausgewiesen.
- Der V1-Vertrag unterscheidet Gruppen-, kombinierte Gruppen-, Selbst-,
  Anmelde- und Nextcloud-Admin-Bedingungen. Fehlerhafte, inkompatible,
  mehrdeutige oder unvollständige Provider werden fail-closed als `UNKNOWN`
  beziehungsweise `UNSUPPORTED` sichtbar; der Consumervertrag ist positiv und
  negativ getestet.
- Jede eigene App hält ihre Projektion bei jeder relevanten Weiterentwicklung
  gemeinsam mit der kanonischen Berechtigungslogik aktuell. Neue oder
  geänderte Rollen, Gruppen, Scopes, Objektgrenzen, Adminausnahmen oder
  bewusst nicht abbildbare Bedingungen werden im selben Feature im Provider
  und in dessen Provider-/Consumer-/Negativtests mitgepflegt. Bekannte
  Vertragsgrenzen bleiben `UNKNOWN` oder `UNSUPPORTED` und werden nie als
  Freigabe ergänzt.
- Eine neue eigene App mit Fachberechtigungen liefert den Provider spätestens
  mit dem ersten betroffenen Feature. Hat sie ausschließlich native
  Nextcloud-Verfügbarkeit oder keine eigenen Fachrechte, wird die
  Nichtanwendbarkeit aus ihrem Zweck begründet und bei Scopeänderungen neu
  geprüft.

### BPM-NEXTCLOUD-NATIVE-PERMISSIONS – Files, Calendar und App-Verfügbarkeit

Status: App-Verfügbarkeit sowie native Gruppenfreigaben und Groupfolders-
Rootrechte umgesetzt; weitere Files-Quellen und Calendar-Details offen

- Für jede aktivierte App bleibt die native App-Verfügbarkeit eine eigene
  Rechtequelle, getrennt von ihrer Detailabdeckung. Eine leere Gruppenbeschränkung
  bedeutet nach `OCP\App\IAppManager::getAppRestriction()`, dass die App nicht
  auf Gruppen begrenzt ist und deshalb für jede erfasste Gruppe als aktiviert
  ausgewiesen wird. Bei einer ausdrücklichen
  Beschränkung erhalten nur die genannten Gruppen `X`; eine nicht belastbar
  lesbare Beschränkung bleibt `UNKNOWN`.
- `UNSUPPORTED` oder `PARTIAL` bei App-Details dürfen eine bekannte globale
  App-Verfügbarkeit nicht verdecken. Umgekehrt darf bloße App-Verfügbarkeit
  keine anwendungsspezifischen Lese-, Schreib-, Verwaltungs- oder
  Freigaberechte vortäuschen.
- Files wird als Zusammenspiel getrennter Quellen bewertet: native
  Gruppenfreigaben, Groupfolders, Files Access Control, externe Speicher und
  weitere offizielle öffentliche Files-Verträge. Dateiinhalte bleiben ausgeschlossen.
  Pfade, Personenbezüge und Share-Metadaten folgen weiterhin
  den dokumentierten Opt-in- und Redaktionsgrenzen.
- Calendar wird nach der App-Verfügbarkeit als vorrangige native
  Detailquelle untersucht. `OCP\Calendar\IManager` und die öffentlichen
  `OCP\Calendar\ICalendar`-Verträge dürfen effektive Rechte eines konkret
  bestimmten Principals liefern. Sie belegen ohne zusätzliche öffentliche
  Quelle jedoch keine vollständige globale Zuordnung aller Calendar-Shares
  zu Nextcloud-Gruppen.
- Vor dem Calendar-Adapter werden deshalb Scan-Scope, Principal-Auswahl,
  Eigentümer-, Share- und Public-Link-Semantik sowie die Vollständigkeitsgrenze
  ausdrücklich festgelegt. Kalender- und Termininhalte bleiben ausgeschlossen.
  Bis dahin sind Calendar-Details sichtbar `UNSUPPORTED`; private
  `OCA\DAV`-Backends, DAV-Tabellen oder Reflection sind kein Ersatz.
- Native Nextcloud-Apps, insbesondere Files und Calendar, werden vor
  beliebigen weiteren Fremd-App-Detailadaptern priorisiert. Jede konkrete
  Umsetzung bleibt an öffentliche APIs, Negativtests und eine ehrliche
  Coverage-Angabe gebunden.

### BPM-THIRD-PARTY-COVERAGE – Fremd-App-Coverage getrennt bewerten

Status: read-only Analyse als nächste Etappe vorgemerkt; Adapter und
Änderungen an fremden Apps nicht freigegeben

- Erst nach der Eigen-App-Abdeckung die tatsächlich aktivierten fremden Apps
  nach Zweck, Berechtigungsmodell und offizieller öffentlicher Schnittstelle
  inventarisieren.
- Öffentliche OCP-, Provider-, Capability- oder dokumentierte Exportverträge
  bevorzugen und eine Providerunterstützung möglichst beim Upstream-Projekt
  anregen.
- Fehlende, inkompatible oder unvollständige Verträge sichtbar als
  `UNKNOWN` oder `UNSUPPORTED` ausweisen; keine Rechte aus Navigation,
  UI-Sichtbarkeit, Klassennamen oder vermuteten Defaults ableiten.
- Einen versionsgebundenen read-only Adapter nur für eine konkret benannte
  App und Version nach separater Risiko-, Datenschutz-, Update- und
  Releaseentscheidung umsetzen. Keine fremden Tabellen, Reflection,
  AppConfig-, Datei-, Volltext- oder Migrator-Fallbacks verwenden.
- Die eng begrenzte Groupfolders-Ausnahme bleibt releasegebunden und ist kein
  allgemeiner Präzedenzfall für andere Fremd-Apps.

### BPM-FOLDER-RIGHTS – Konkrete Ordnerrechte ausweisen

Status: native Nextcloud-Gruppenfreigaben und Groupfolders 22.x umgesetzt; weitere Adapter offen

- Für jeden belastbar auslesbaren konkreten Ordner und jede erfasste Gruppe
  getrennt Lesen, Schreiben beziehungsweise Ändern, Ausführen und Löschen
  darstellen.
- Native zusätzliche Rechte wie Erstellen und Teilen getrennt abbilden.
  `Ausführen` nur anzeigen, wenn die konkrete Quelle dieses Recht wirklich
  liefert; sonst `nicht anwendbar` oder `UNKNOWN`.
- Group Folders, Files Access Control, Shares und externe Speicher jeweils
  über eigene read-only Adapter mit Quellen- und Confidence-Angabe behandeln.
- Konkrete Ordner in der geschützten Matrix sichtbar machen, aber die
  bestehende standardmäßige Pfadredaktion in Exporten und den Ausschluss von
  Dateiinhalten, Credentials und unnötigen Personenbezügen erhalten.
- Pro Adapter positive Rechte, Deny, Vererbung, widersprüchliche Regeln,
  unbekannte Quelle, deaktivierte App, Redaction und unveränderte
  Nextcloud-Rechte testen.

Umgesetzt für native Nextcloud-Gruppenfreigaben:

- Öffentliche `OCP\Share\IManager`-Quelle mit read-only Abfrage einschließlich
  direkter Freigaben und Weiterfreigaben.
- Getrennte R/W/C/D/S-Zeilen, explizites `n/a` für Ausführen sowie
  fail-closed Behandlung von Teilständen, unbekannten Gruppen, Permission-Bits,
  Node-Typen, Pfaden und widersprüchlichen Datensätzen.
- Standardmäßig deaktivierte Einzelfreigaben-Erfassung und pfadredigierte
  JSON-/CSV-/Markdown-/HTML-Exporte einschließlich Diffangaben.

Umgesetzt für Groupfolders 22.x auf Nextcloud 34:

- Versionsgebundene read-only Projektion der Root-Rechte aus dem offiziellen
  `FolderManager`, ohne fremde Tabellen, Reflection oder Schreibzugriff.
- Ausschluss von Mount-Pfaden, Datei-/Ordnernamen und Dateiinhalten;
  persistiert werden nur pseudonyme Ordnerreferenzen, Gruppen-IDs,
  Permission-Masken und der kontrollierte Vollständigkeitsstatus.
- Sichtbarer `UNKNOWN`-Fallback für abweichende Versionen, die historische
  App-ID, Team-/Circle-Zuordnungen, erweiterte ACLs und Vertragsfehler.
- Verbindlicher Release-Check gegen einen frischen offiziellen
  Groupfolders-Checkout, damit Änderungen der fremden Vorgehensweise nicht
  stillschweigend übernommen werden.

Offen bleiben die vollständige Abbildung erweiterter Groupfolders-ACLs,
Files Access Control und externe Speicher. Sie
werden nicht aus internen App-Klassen oder fremden Tabellen gelesen.

### BPM-L10N – app-lokale Matrixdarstellung lokalisieren

Aktivierung ausschließlich nach Freigabe des systemweiten Root-Vorhabens
`ZM-06`. Sichtbare Status-, Warn-, Export- und Fehlermeldungen wechseln auf
Nextcloud-l10n; Berechtigungsschlüssel, Effekte, Scope-IDs, Snapshotdaten und
Adapterstatus bleiben sprachneutral. Eine gewählte Exportlocale muss
reproduzierbar sein.

## Aktueller Fokus

- Die Eigen-App-Providerbasis einschließlich `filzmann_data_protection` ist
  umgesetzt. Als nächste Produktlücke native Files-/Calendar-Coverage und
  danach Fremd-App-Coverage ausschließlich über belastbare öffentliche
  Quellen erweitern.
- Für `filzmann_permission_matrix` bleibt der eigene
  `PermissionMatrixAccessAdapter` die direkte Projektion derselben
  `ConfigService`, die der serverseitige `AccessService` nutzt; ein
  Event-Rücklauf der App zu sich selbst ist nicht erforderlich. LocalBase und
  OrgSuite besitzen im aktuellen Zweck keine eigenen Fachrechte, müssen diese
  Nichtanwendbarkeit aber bei Scopeänderungen neu bewerten.
- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Den versionierten AD-Organisationsvertrag und seinen kontrollierten
  `UNKNOWN`-Fallback auf Staging abnehmen.
- Sicherstellen, dass unbekannte, nicht eindeutige oder nicht unterstützte
  Rechtequellen nie als freigegeben erscheinen.
- Viewer-, Admin-, Export- und Auditgrenzen auf einem realitätsnahen Staging
  positiv und negativ abnehmen.

## Geplante Erweiterungen

- Erweiterte Team-Folder-ACLs sowie externe Speicherrechte.
- Files-Access-Control-Regelgruppen.
- Calendar-Detailadapter nach Klärung der gruppenbezogenen öffentlichen
  Coverage-Grenze.
- Danach app-spezifische Detailadapter für Deck, Collectives, Tables, Talk,
  Contacts, Forms, Notes, Richdocuments, OnlyOffice, LDAP und Two-Factor.
- XLSX- und PDF-Export nach geklärtem Datenschutz- und
  Dokumenterzeugungsvertrag.

## Vor der Umsetzung zu klären

- Adaptereigentümer, Unknown-/Unsupported-Fallback und vollständige
  Allow-/Deny-Fälle je neuer Rechtequelle.
- Personenbezug, Pfadredaktion und Aufbewahrung je neuem Exportformat.
