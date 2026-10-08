# Öffentlicher Berechtigungsprovider V1

Die Berechtigungsmatrix entdeckt app-eigene Detailrechte ausschließlich über
den optionalen read-only Vertrag im Namespace
`OCA\FlzPermissionMatrix\PublicApi\V1`. Die Fachapp bleibt Eigentümerin
ihrer Regeln. Der Consumer liest weder ihre Tabellen noch ihre privaten
Services oder Konfigurationen direkt.

## Registrierung

Eine Fachapp registriert einen Listener für
`RegisterPermissionProvidersEvent`. Der Listener wird nur ausgelöst, wenn die
Berechtigungsmatrix installiert ist, und übergibt genau einen
`PermissionProvider` für die eigene App-ID. Der Descriptor verwendet
Vertragsversion `1.0` und Capability `permissions`.

```php
final class PermissionProviderListener {
    public function __construct(private AppPermissionProvider $provider) {}

    public function handle(object $event): void {
        if ($event instanceof RegisterPermissionProvidersEvent) {
            $event->register($this->provider);
        }
    }
}
```

Fehlende, inkompatible oder doppelte Provider werden niemals gewählt. Die
App-Verfügbarkeit aus der nativen Nextcloud-Gruppeneinschränkung bleibt
sichtbar; die Detailabdeckung lautet `UNSUPPORTED` oder `UNKNOWN`.

## Regeln und Akteursbedingungen

`PermissionProvider::collect()` liefert ein `PermissionProviderResult` mit
einer Liste unveränderlicher `PermissionRule`-Objekte, Vollständigkeitsstatus
und datensparsamen Warnungen. Eine Regel benennt Objektart und -bezeichnung,
Detail, stabilen Permission-Key, Anzeigetext, `allow` oder `deny`, Scope,
technische Quelle und Confidence (`high`, `medium`, `low`). Sie enthält keine
Benutzerlisten und keine Dateiinhalte.

Zulässige Bedingungen:

- `PermissionCondition::group($groupId)` für eine konkrete Nextcloud-Gruppe;
- `all([...])` für Bedingungen, die gemeinsam erfüllt sein müssen;
- `any([...])` für Alternativen;
- `self()` für eigene Datensätze oder die eigene Person;
- `authenticated()` für jede angemeldete Person;
- `nextcloudAdmin()` für native Nextcloud-Administration.
- `temporaryAppAdminGrant()` für eine aktuell aktive, app-lokal persistierte
  und zeitlich begrenzte Admin-Vollzugriffsfreigabe. Fachapps kombinieren sie
  mit `nextcloudAdmin()` über `all([...])`; sie enthält keine UID oder
  Freigabehistorie.

Nur ausschließlich gruppenbezogene Bedingungen werden auf Gruppenfelder
projiziert. `all` wird dabei als `AND` markiert. Sobald eine Bedingung einen
nicht gruppenbezogenen Akteur enthält, bleiben alle Gruppenfelder `n/a`; die
vollständige Bedingung bleibt in `access_rules` erhalten. Providerfehler werden
ohne Exceptiontext als `UNKNOWN` sichtbar.

Ein `complete: false` ist verpflichtend, sobald dynamische Ausnahmen oder
weitere relevante Berechtigungspfade nicht vollständig beschrieben sind. Die
gelieferten Regeln dürfen sichtbar bleiben, erhalten dann aber ebenfalls den
Status `UNKNOWN` und niedrige Gesamt-Confidence.
