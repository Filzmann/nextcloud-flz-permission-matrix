# Architektur – Berechtigungsmatrix

## Verantwortung

Die Berechtigungsmatrix ist eine eigenständige read-only App im Portfolio
IKT/Datenschutz. Sie inventarisiert und bewertet Berechtigungsquellen, ändert
aber weder Nextcloud-Rechte noch Konfigurationen anderer Apps.

## Quellen und Coverage

- Scanner, Matrixaufbau, Baseline/Diff, Export, Zugriffskontrolle und
  Persistenz bleiben getrennte Komponenten.
- App-eigene Berechtigungen werden ausschließlich über öffentliche
  Permission-Provider gelesen. Native Nextcloud-Rechte und optionale
  Fremd-App-Adapter bleiben getrennte Quellen.
- Semantische Rollen und Bereiche stammen ausschließlich aus
  `OCA\\LocalBase\\PublicApi\\V1`. Die Matrix prüft App-Aktivierung und
  Vertragsversion vor der Nutzung; das Provider-DTO verantwortet
  Gültigkeit, eindeutige Gruppenabbildungen und Prüfsumme.
- Fehlende, mehrdeutige oder inkompatible Quellen liefern `UNKNOWN`,
  `UNSUPPORTED` oder eine sichtbar partielle Coverage und niemals eine
  vermutete Freigabe.
- Die eng begrenzte Groupfolders-Ausnahme ist versionsgebunden und wird vor
  Releases gegen den offiziellen Quellstand geprüft.

Der Matrixsnapshot hält den LocalBase-Status als `VALID`, `MISSING`,
`INVALID`, `INCOMPATIBLE` oder `UNAVAILABLE` fest. Nur `VALID` liefert
semantische Zuordnungen. LocalBase bleibt eine optionale Laufzeit-App; ihr
Fehlen verhindert weder Installation noch Scan oder Darstellung der
Berechtigungsmatrix.

## Datenschutz und Rechte

Standardscans und Exporte vermeiden Benutzerlisten, Secrets, Dateiinhalte und
personenbezogene Pfade. Viewer-, Administrations-, Baseline-, Scan- und
Exportrechte werden serverseitig geprüft. Native Administration allein
erteilt keinen fachlichen Vollzugriff.

Die app-lokale Freigabesteuerung liegt rollenabhängig im Haupteinstieg und
nicht im technischen Nextcloud-Adminbereich. Nur Mitglieder der kanonischen
Gruppe `Datenschutzbeauftragte` dürfen Historie, Erteilung und Widerruf
verwenden; Ziel bleibt ein aktuelles natives Administrationskonto. Ein
kombiniertes Admin-/Datenschutzkonto ohne eigene aktive Freigabe erhält einen
direkten Link zur Steuerung, während die Matrixdaten weiterhin getrennt
serverseitig geschützt bleiben. Freigabezeiträume bilden zugleich den
minimalen Auditnachweis.

Der statische Processing-Katalog in `resources/privacy-processing.json` ist
die app-eigene Policyquelle für Snapshot/Matrix, Export, Audit und temporäre
Adminfreigaben. Er wird über den öffentlichen
`OCA\FilzmannDataProtection\PublicApi\V1`-Vertrag lazy registriert und enthält
keine Laufzeitdatensätze. PersonalData- und Retention-Provider lesen weiterhin
nur app-eigene Laufzeitdaten; sie werden nicht durch den Katalog ersetzt.
Offene Rechtsgrundlagen, Fachverantwortung, Backup-, Einschränkungs- oder
Löschentscheidungen bleiben explizit `PRIVACY-DECISION-REQUIRED`.
