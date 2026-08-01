# Roadmap – Berechtigungsmatrix

Diese Datei enthält ausschließlich zukünftige Adapter, Exportziele und offene
Produktentscheidungen. Der geltende read-only Sicherheitsvertrag steht in
`AGENTS.md`; der aktuelle Funktionsumfang steht in `README.md`.

Die Berechtigungsmatrix steht noch am Anfang ihrer Entwicklung. Die folgenden
Aufgaben legen deshalb fachliche Zielverträge und Sicherheitsgrenzen fest,
ohne das heutige Matrixmodell vorschnell als endgültige Struktur zu behandeln.

## Freigegebene Umsetzungsaufgaben

### BPM-AD-ORG-SNAPSHOT – Gruppen fachlich aus dem Organisationssnapshot deuten

Status: bereit nach `LB-AD-ORG-SNAPSHOT`

- Den validierten LocalBase-Snapshot read-only konsumieren und seine Version
  sowie Prüfsumme mit jedem Matrixsnapshot festhalten.
- Rohgruppen und rohe Matrixzellen für Revision, Diff und Export unverändert
  erhalten. Historische Scans niemals mit einer späteren
  Organisationskonfiguration neu deuten.
- Bei fehlendem, ungültigem oder inkompatiblem Provider Gruppen einzeln
  anzeigen und die fachliche Bedeutung als `UNKNOWN` markieren.
- Die bestehende App-Nutzungsregel beibehalten: Eine native
  Nextcloud-Gruppeneinschränkung begrenzt die App; ohne Einschränkung ist
  `app.use` für alle erfassten Gruppen erlaubt. Fehlende Detailadapter bleiben
  davon getrennt `UNSUPPORTED`.
- Provider-/Consumer-, historische Snapshot-, Allow-/Deny-,
  Unknown-/Unsupported- und Exporttests gemeinsam mit
  `LB-AD-ORG-SNAPSHOT` abnehmen.

### BPM-FOLDER-RIGHTS – Konkrete Ordnerrechte ausweisen

Status: bereit für adapterweise Umsetzung

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

### BPM-L10N – Berechtigungsmatrix vollständig lokalisieren

Status: bereit nach Stabilisierung des Matrixmodells

- Oberfläche, Status-, Warn-, Export- und Fehlermeldungen auf
  Nextcloud-l10n umstellen.
- Berechtigungsschlüssel, Effekte, Scope-IDs, Snapshotdaten und Adapterstatus
  sprachneutral lassen; lokalisierte Labels erst bei Darstellung und Export
  erzeugen.
- Deutsche Ausgabe, eine weitere Locale, Fallback, Pluralformen,
  Platzhalter, Escaping sowie reproduzierbare Exportlocale testen.
- Erst nach vollständiger Migration einen Rohtext-Check für die App
  verbindlich schalten.

## Aktueller Fokus

- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Die heutige Gruppenfamilienerkennung gegen den konfigurierbaren
  AD-Organisationsvertrag prüfen.
- Sicherstellen, dass unbekannte, nicht eindeutige oder nicht unterstützte
  Rechtequellen nie als freigegeben erscheinen.
- Viewer-, Admin-, Export- und Auditgrenzen auf einem realitätsnahen Staging
  positiv und negativ abnehmen.

## Geplante Erweiterungen

- Konkrete Team- und Group-Folder-Rechte.
- Files-Access-Control-Regelgruppen.
- App-spezifische Detailadapter für Deck, Collectives, Tables, Talk,
  Calendar, Contacts, Forms, Notes, Richdocuments, OnlyOffice, LDAP und
  Two-Factor.
- XLSX- und PDF-Export nach geklärtem Datenschutz- und
  Dokumenterzeugungsvertrag.

## Vor der Umsetzung zu klären

- Kanonischer read-only Organisationssnapshot für AD-Rollen, Bereiche und
  Assistenzteams.
- Behandlung historischer Rohgruppen und Snapshots bei einer Umstellung der
  Gruppenfamilienerkennung.
- Adaptereigentümer, Unknown-/Unsupported-Fallback und vollständige
  Allow-/Deny-Fälle je neuer Rechtequelle.
- Personenbezug, Pfadredaktion und Aufbewahrung je neuem Exportformat.
