# Roadmap – Berechtigungsmatrix

Diese Datei enthält ausschließlich offene Adapter, Exportziele,
Produktentscheidungen und Freigabegates. Der aktuelle Coverage-Stand steht in
`README.md`, erledigte Änderungen in `CHANGELOG.md` und geltende
Architektur in `docs/architecture.md`.

## Nextcloud-Kompatibilitätsgate

### BPM-NC-COMPAT – Core-Bereich 29–35 und optionale Adapter getrennt belegen

Vor dem nächsten Release werden alle deklarierten Majors lückenlos mit Fresh
Install/Upgrade, DI, Provider-Scan, Permission-V1-Vertrag, Assets und
sichtbarer Matrix geprüft. Die private Groupfolders-Ausnahme bleibt separat
versionsgebunden und liefert auf unbekannten Kombinationen sichtbar
`UNKNOWN` oder `UNSUPPORTED`. Eine höhere `max-version` folgt nur aus dem
app-lokalen `verify-nextcloud-future-compatibility`-Nachweis.

## Priorisierte offene Adapter

### BPM-NEXTCLOUD-NATIVE-PERMISSIONS – Files und Calendar vervollständigen

- Die eigene PermissionProvider-Projektion bei jeder relevanten Weiterentwicklung
  gegen neue oder geänderte Berechtigungen und Scopes abgleichen.
- Eine leere Gruppenbeschränkung als appweite Verfügbarkeit für jede erfasste
  Gruppe behandeln; fehlende Detailabdeckung bleibt davon getrennt
  `UNSUPPORTED`.
- Files Access Control, externe Speicher und weitere öffentliche
  Files-Verträge als getrennte Quellen bewerten.
- Metadaten und Rechte nur über öffentliche Files-Verträge erfassen;
  Dateiinhalte bleiben ausgeschlossen.
- Für Calendar Scan-Scope, Principal-Auswahl, Eigentümer-, Share- und
  Public-Link-Semantik sowie die Vollständigkeitsgrenze auf Basis von
  `OCP\Calendar\IManager` festlegen.
- Ausschließlich öffentliche OCP-Verträge verwenden; private DAV-Backends,
  fremde Tabellen oder Reflection bleiben ausgeschlossen.

### BPM-FOLDER-RIGHTS – weitere konkrete Ordnerrechte

- Erweiterte Groupfolders-ACLs, Files Access Control und externe Speicher
  jeweils über eigene read-only Adapter mit Quellen- und Confidence-Angabe
  abbilden.
- Lesen, Schreiben/Ändern, Erstellen, Löschen, Teilen und nur tatsächlich
  gelieferte Ausführungsrechte getrennt darstellen.
- Pfadredaktion, Datenschutzgrenzen sowie positive, negative, unbekannte und
  widersprüchliche Zustände testen.

### BPM-THIRD-PARTY-COVERAGE – Fremd-App-Coverage kontrolliert bewerten

- Nach den nativen Quellen aktivierte Fremd-Apps nach Zweck,
  Berechtigungsmodell und offizieller öffentlicher Schnittstelle
  inventarisieren.
- Fehlende oder unvollständige Verträge sichtbar fail-closed behandeln.
- Einen versionsgebundenen Adapter nur nach separater Risiko-, Datenschutz-,
  Update- und Releaseentscheidung implementieren.

## Systemweit gegatete Aufgabe

### BPM-L10N – Matrix und Exporte lokalisieren

Aktivierung ausschließlich nach Freigabe von Root-Vorhaben `ZM-06`.
Sichtbare Meldungen dürfen lokalisiert werden; Berechtigungsschlüssel,
Effekte, Scope-IDs, Snapshotdaten und Adapterstatus bleiben sprachneutral.
Eine Exportlocale muss reproduzierbar sein.

## Staging und weitere Produkte

- Viewer-, Admin-, Export- und Auditgrenzen positiv und negativ prüfen.
- Danach Detailadapter für Deck, Collectives, Tables, Talk, Contacts, Forms,
  Notes, Richdocuments, OnlyOffice, LDAP und Two-Factor einzeln bewerten.
- XLSX- und PDF-Export erst nach geklärtem Datenschutz-,
  Dokumenterzeugungs- und Aufbewahrungsvertrag ergänzen.

Vor jedem neuen Adapter sind Eigentümer, Coverage, Unknown-/Unsupported-
Fallback, Allow-/Deny-Fälle, Personenbezug und Pfadredaktion festzulegen.
