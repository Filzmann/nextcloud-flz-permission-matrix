# Roadmap – Berechtigungsmatrix

Diese Datei enthält ausschließlich zukünftige Adapter, Exportziele und offene
Produktentscheidungen. Der geltende read-only Sicherheitsvertrag steht in
`AGENTS.md`; der aktuelle Funktionsumfang steht in `README.md`.

## Aktueller Fokus

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
