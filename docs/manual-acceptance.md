# Manuelles Abnahmeformular – Berechtigungsmatrix

Dieses Formular dokumentiert die fachliche, visuelle und sicherheitsbezogene
Abnahme der read-only Berechtigungsmatrix auf einem realitätsnahen
Staging-System. Pro Prüffall wird genau ein Ergebnis markiert und unter
„Warum/Beleg/Abweichung“ knapp festgehalten, was beobachtet wurde.

Keine Passwörter, Tokens, privaten Schlüssel, Dateiinhalte, personenbezogenen
Echtdaten oder vollständigen internen Pfade eintragen. Ausschließlich neutrale
Testgruppen, synthetische Snapshots und redigierte Belege verwenden.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum und Uhrzeit | |
| Prüfer*in | |
| Umgebung und URL | |
| App-Version | |
| Nextcloud-Version | |
| Browser und Version | |
| Fenstergröße / Zoom | |
| Neutrale Viewer-, Admin- und Deny-Konten | |
| Getesteter Snapshot / Baseline | |

Ergebniskennzeichnung: `[ ] erfolgreich` / `[ ] nicht erfolgreich` /
`[ ] nicht geprüft`. Bei „nicht erfolgreich“ oder „nicht geprüft“ ist eine
Begründung verpflichtend.

## A. Zugriff und read-only Grundvertrag

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| A1 | Viewer-Zugriff | Mit einem Konto aus einer konfigurierten Viewer-Gruppe die App öffnen und vorhandene Snapshots ansehen. | Matrix und erlaubte Exporte sind sichtbar; Verwaltungsaktionen fehlen oder sind nicht ausführbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A2 | Matrix-Admin | Mit einem Konto aus einer konfigurierten Admin-Gruppe die App öffnen, einen Scan starten und eine Baseline setzen. | Die vorgesehenen Verwaltungsaktionen sind serverseitig erlaubt und nachvollziehbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A3 | Nextcloud-Admin | Dieselben Verwaltungswege zuerst ohne und danach mit aktiver app-lokaler Freigabe prüfen. | Nativer Adminstatus allein legt keine Matrixdaten offen; erst die höchstens 24 Stunden gültige, UID-genaue Freigabe erlaubt den vorgesehenen fachlichen Zugriff. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A4 | Nicht berechtigtes Konto | App, Snapshot, Export und Scan mit einem Konto außerhalb aller erlaubten Gruppen direkt aufrufen. | Alle Wege werden serverseitig verweigert; keine Matrixdaten werden offengelegt und kein Scan gestartet. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A5 | Keine Rechteänderung | Vor und nach Scan, Filterung, Baseline und Export eine vorbereitete Nextcloud-Berechtigung vergleichen. | Die Quellberechtigung bleibt unverändert; die App schreibt ausschließlich ihre eigenen Matrixdaten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A6 | Eigenständige Navigation | App über ihren eigenen Nextcloud-Haupteinstieg öffnen und denselben Pfad mit einem nicht berechtigten Konto direkt aufrufen. | Der Einstieg erscheint nur für berechtigte Konten; der direkte Aufruf bleibt zusätzlich serverseitig geschützt und es gibt keinen OrgSuite-/BR-Einstieg. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## B. Scan und Matrixdarstellung

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| B1 | Manueller Scan | Mit Verwaltungsrecht einen Scan starten und nach Abschluss Übersicht, Apps, Gruppen und Snapshotliste öffnen. | Ein neuer Snapshot erscheint mit konsistenten Zählwerten und verständlichem Status. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B2 | Nicht unterstützte App | Eine aktivierte App ohne Detailadapter in den Scan aufnehmen. | Sie wird als `UNSUPPORTED` beziehungsweise mit fehlender Detailabdeckung sichtbar und niemals still als freigegeben dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B3 | Unklare Quelle | Eine ausschließlich für Tests vorbereitete nicht eindeutig auslesbare Rechtequelle prüfen. | Die betroffenen Werte erscheinen als `UNKNOWN`; aus Unsicherheit entsteht keine Freigabe. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B4 | App-Sortierung und Klappen | Apps einzeln sowie gemeinsam ein- und ausklappen. | Matrixzeilen bleiben streng nach App gegliedert; Klappen verändert nur die Darstellung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B5 | Gruppenansichten | Zwischen Teams, Gruppenfamilien und Rohgruppen wechseln. | Gruppierung ist nachvollziehbar; Rohgruppen bleiben erhalten und gemischte Teilbelegungen werden nicht zu einem Einzelrecht verschmolzen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B6 | Filter | Nach App, Gruppe, Status, Detailabdeckung und Text einzeln sowie kombiniert filtern und zurücksetzen. | Nur passende Matrixwerte erscheinen; Rücksetzen stellt den vollständigen Snapshot wieder her. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B7 | Fokus auf Gruppe | Eine Gruppenüberschrift anklicken beziehungsweise per Tastatur aktivieren. | Die passende Spalte wird fokussiert und verständlich markiert, ohne Daten zu filtern oder Rechte zu ändern. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## C. Baseline, Diff und Strict Mode

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| C1 | Baseline setzen | Einen synthetischen freigegebenen Snapshot als Baseline markieren und neu laden. | Genau dieser Snapshot bleibt als Baseline erkennbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C2 | Erweiterte Berechtigung | Eine neutrale Testberechtigung in der Quelle erweitern, neu scannen und den Diff öffnen. | Die Erweiterung wird gegenüber der Baseline sichtbar und nicht als unverändert dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C3 | Entfernte Berechtigung | Die Testberechtigung wieder entfernen und erneut scannen. | Die Entfernung wird mit korrekter Richtung ausgewiesen; historische Snapshots bleiben unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C4 | Strict Mode | Strict Mode aktivieren und einen neuen oder nicht vollständig adaptergestützten Wert scannen. | Der Wert wird compliance-relevant beziehungsweise nicht freigegeben bewertet, nicht still akzeptiert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C5 | Unberechtigte Baseline | Als reines Viewer-Konto eine Baseline über UI und direkten Request setzen. | Beide Wege werden abgewiesen; die bestehende Baseline bleibt unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## D. Exporte und Datenschutz

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| D1 | Erlaubte Formate | Den gleichen Snapshot als JSON, CSV, Markdown und HTML exportieren und die Kernaussagen vergleichen. | Alle erlaubten Formate sind lesbar und bilden Snapshot, Status und Gruppen konsistent ab. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D2 | Serverseitige Formatgrenze | Ein in der Administration nicht erlaubtes Format über einen direkten Request anfordern. | Der Export wird serverseitig abgewiesen und es entsteht keine Ausgabedatei. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D3 | Standardredaktion | Mit `include_users=false`, `redact_paths=true` und `include_share_metadata=false` exportieren. | Keine Benutzerlisten, Share-Metadaten, Dateiinhalte, Secrets oder vollständigen personenbezogenen Pfade sind enthalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D4 | Bewusste Zusatzdaten | Ausschließlich mit synthetischen Testdaten Benutzerlisten beziehungsweise Share-Metadaten administrativ aktivieren und erneut exportieren. | Nur ausdrücklich aktivierte Daten erscheinen; die Änderung erweitert weder Scan- noch App-Zugriffsrechte. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D5 | Viewer-Exportgrenze | Als Viewer ein erlaubtes und ein nicht erlaubtes Format versuchen; als unberechtigtes Konto denselben Download direkt aufrufen. | Viewer erhält nur erlaubte Exporte; das unberechtigte Konto erhält keine Daten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## E. Audit, Administration und Bedienbarkeit

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| E1 | Auditierbare Aktionen | App-Aufruf, Export, Scan und Baseline-Aktion mit neutralen Konten ausführen und den Auditstand administrativ prüfen. | Zeitpunkt, Benutzer-ID, Aktion, Export-Flag und gegebenenfalls Snapshot-ID werden erfasst, ohne sensible Inhalte zu protokollieren. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E2 | Adminvalidierung | Ungültige Gruppen, Scanintervalle, Aufbewahrungswerte und Exportformate speichern. | Ungültige Werte werden verständlich abgewiesen; es entsteht keine Teilkonfiguration. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E3 | CSRF-Schutz | Einen schreibenden Scan-, Baseline- oder Konfigurationsrequest ohne gültiges Requesttoken senden. | Der Request wird abgewiesen und verändert keinen Zustand. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E4 | Tastatur und Fokus | Tabs, Filter, Klappsteuerung, Matrix, Snapshotliste und Adminformular nur mit Tastatur bedienen. | Alle Funktionen sind erreichbar, Fokus ist sichtbar und Status wird nicht ausschließlich farblich vermittelt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E5 | Große Matrix | Viele Apps und Gruppen bei kleinem Fenster anzeigen und in beide Richtungen scrollen. | Matrix und Snapshotliste bleiben innerhalb ihrer Wrapper bedienbar; Kopf- und Kontextinformationen bleiben verständlich. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E6 | Datensparsame Abnahme | Formular, Screenshots, Exporte und Auditbelege prüfen. | Es wurden nur synthetische beziehungsweise redigierte Daten dokumentiert; keine Secrets oder Dateiinhalte sind enthalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E7 | Datenschutz-REVIEW-Fristen | Für Exportmetadaten und Auditprotokolle unterschiedliche Fristen speichern und die Art.-15-Auskunft mit synthetischen alten Datensätzen öffnen. | Beide Werte bleiben unabhängig erhalten; fällige Datensätze erscheinen als `REVIEW erforderlich`, ohne dass ein Datensatz gelöscht wird. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E8 | DPO-gesteuerte Adminfreigabe | Mit Datenschutzbeauftragten-Konto ohne nativen Adminstatus Historie lesen, einem aktuellen Test-Admin maximal 24 Stunden Zugriff erteilen und widerrufen; dieselben Requests als nativer Admin allein, gewöhnliches Konto und mit manipuliertem Ziel wiederholen. | Nur die Datenschutzrolle kann steuern; ungültige und unberechtigte Fälle bleiben mutationsfrei, Schreibrequests ohne CSRF-Token werden abgewiesen, und die Freigabehistorie nennt Beginn, Ende sowie handelnde UIDs. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |


## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Anzahl erfolgreich | |
| Anzahl nicht erfolgreich | |
| Anzahl nicht geprüft | |
| Kritische Abweichungen / Ticketreferenzen | E5: Die horizontale Scrollleiste war nicht dauerhaft im sichtbaren Matrixbereich erreichbar; siehe `BPM-MATRIX-SCROLL` in `ROADMAP.md`. |
| Erneute Prüfung erforderlich bis | |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Begründung der Gesamtentscheidung | |
| Name / Datum | |
