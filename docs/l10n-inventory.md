# L10N-Inventar – Berechtigungsmatrix (`flz_permission_matrix`)

Stand: migriert (ZM-06). Quellsprache ist Deutsch (Suite-Konvention); sichtbare
Texte sind mit `IL10N::t()` bzw. `window.t('flz_permission_matrix', …)` markiert; die Kataloge
`l10n/en.json`/`en.js` und `l10n/en_GB.json`/`en_GB.js` liefern Englisch. Ohne
Katalogeintrag greift der deutsche Quelltext; die Locale kommt aus Nextcloud
(`OC.getLocale()`, serverseitig `config.php locale`), fachlicher Fallback `de-DE`.

`tests/Ui/L10nCatalogContractTest.php` erzwingt Katalogvollständigkeit für alle
`t()`-Schlüssel und Ausnahmetexte.
