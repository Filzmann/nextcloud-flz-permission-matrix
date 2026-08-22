# Roadmap – Berechtigungsmatrix

Diese Datei enthält ausschließlich zukünftige Adapter, Exportziele und offene
Produktentscheidungen. Der geltende read-only Sicherheitsvertrag steht in
`AGENTS.md`; der aktuelle Funktionsumfang steht in `README.md`.

Die Berechtigungsmatrix steht noch am Anfang ihrer Entwicklung. Die folgenden
Aufgaben legen deshalb fachliche Zielverträge und Sicherheitsgrenzen fest,
ohne das heutige Matrixmodell vorschnell als endgültige Struktur zu behandeln.

## Freigegebene Umsetzungsaufgaben

### BPM-CUSTOM-APP-PERMISSIONS – Berechtigungen aus der zuständigen App lesen

Status: fachlicher Zielvertrag; appübergreifende Umsetzung noch ausstehend

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
- Provider-/Consumer-Vertrag, Versionierung, Fehlerfälle sowie positive und
  negative Berechtigungsfälle werden vor der ersten appübergreifenden
  Umsetzung gemeinsam festgelegt und getestet.

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

## Zukunftsplanung – nicht freigegeben

### BPM-L10N – Berechtigungsmatrix vollständig lokalisieren

Status: später, nicht freigegeben; Matrixmodell, Pilot-App, Reihenfolge und
Rohtext-Gate werden vor jeder Umsetzung appübergreifend separat freigegeben

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
- Den versionierten AD-Organisationsvertrag und seinen kontrollierten
  `UNKNOWN`-Fallback auf Staging abnehmen.
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

- Adaptereigentümer, Unknown-/Unsupported-Fallback und vollständige
  Allow-/Deny-Fälle je neuer Rechtequelle.
- Personenbezug, Pfadredaktion und Aufbewahrung je neuem Exportformat.
