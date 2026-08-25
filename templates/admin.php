<?php
script('filzmann_permission_matrix', 'modules/api');
script('filzmann_permission_matrix', 'admin');
style('filzmann_permission_matrix', 'style');
$config = $_['config'];
?>

<div id="pm-admin" class="pm-admin">
    <h2>Berechtigungsmatrix</h2>
    <section aria-labelledby="pm-full-access-heading">
        <h3 id="pm-full-access-heading">Zeitlich begrenzter Admin-Vollzugriff</h3>
        <p>Native Nextcloud-Administration erteilt kein automatisches Verwaltungsrecht für die Berechtigungsmatrix. Eine Freigabe gilt nur für das angegebene Administrationskonto. Maximal 24 Stunden sind zulässig.</p>
        <form id="pm-full-access-form"><label>Admin-Benutzerkennung <input name="targetUid" required maxlength="64" autocomplete="off"></label><label>Dauer <select name="durationMinutes" required><option value="60">1 Stunde</option><option value="240">4 Stunden</option><option value="480">8 Stunden</option><option value="1440">24 Stunden</option></select></label><label><input id="pm-full-access-enabled" name="enabled" type="checkbox" required> Vollzugriff für diesen Zeitraum aktivieren</label><button type="submit">Freigabe aktivieren</button></form>
        <p id="pm-full-access-status" role="status" aria-live="polite"></p>
        <table><caption>Protokollierte Admin-Vollzugriffszeiträume</caption><thead><tr><th>Ziel-Admin</th><th>Freigegeben von</th><th>Von</th><th>Geplant bis</th><th>Tatsächlich bis / Status</th><th>Aktion</th></tr></thead><tbody id="pm-full-access-history"><tr><td colspan="6">Freigaben werden geladen.</td></tr></tbody></table>
    </section>
    <form id="pm-admin-form">
        <label>
            Leseberechtigte Gruppen
            <textarea name="viewer_groups" rows="4" aria-describedby="pm-viewer-groups-help"><?php p(implode("\n", $config['viewer_groups'])); ?></textarea>
        </label>
        <p id="pm-viewer-groups-help">
            Üblich ist der lesende Zugriff für den Betriebsrat oder Personalrat.
            IKT-Ausschuss und Datenschutzbeauftragte können für Prüfung und Beratung ergänzt werden.
            Die Gruppennamen sind vollständig an die eigene Organisation anpassbar.
        </p>
        <label>
            Admin-Gruppen
            <textarea name="admin_groups" rows="3" aria-describedby="pm-admin-groups-help"><?php p(implode("\n", $config['admin_groups'])); ?></textarea>
        </label>
        <p id="pm-admin-groups-help">
            Beispielsweise IT-Administration. Verwaltungsrechte umfassen Konfiguration,
            Scans und Baseline-Freigaben und sollten restriktiv vergeben werden.
        </p>
        <label>
            Scan-Intervall
            <select name="scan_interval">
                <?php foreach (['hourly' => 'stuendlich', 'daily' => 'taeglich', 'weekly' => 'woechentlich'] as $value => $label): ?>
                    <option value="<?php p($value); ?>" <?php if ($config['scan_interval'] === $value) { print_unescaped('selected'); } ?>><?php p($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><input type="checkbox" name="strict_mode" value="1" <?php if ($config['strict_mode']) { print_unescaped('checked'); } ?>> Strict Mode</label>
        <label><input type="checkbox" name="include_users" value="1" <?php if ($config['include_users']) { print_unescaped('checked'); } ?>> Benutzerlisten in Exporten</label>
        <label><input type="checkbox" name="redact_paths" value="1" <?php if ($config['redact_paths']) { print_unescaped('checked'); } ?>> Pfade redigieren</label>
        <label><input type="checkbox" name="include_share_metadata" value="1" <?php if ($config['include_share_metadata']) { print_unescaped('checked'); } ?>> Share-Metadaten aufnehmen</label>
        <label>
            Exportformate
            <input type="text" name="export_formats" value="<?php p(implode(',', $config['export_formats'])); ?>">
        </label>
        <label>
            Snapshot-Retention
            <input type="number" min="1" max="500" name="retention" value="<?php p((string)$config['retention']); ?>">
        </label>
        <fieldset>
            <legend>Datenschutz-REVIEW</legend>
            <label>
                Aufbewahrungsfrist für Exportmetadaten in Tagen
                <input type="number" min="1" max="3650" name="export_metadata_retention_days" value="<?php p((string)$config['export_metadata_retention_days']); ?>" aria-describedby="pm-retention-review-help">
            </label>
            <label>
                Aufbewahrungsfrist für Auditprotokolle in Tagen
                <input type="number" min="1" max="3650" name="audit_retention_days" value="<?php p((string)$config['audit_retention_days']); ?>" aria-describedby="pm-retention-review-help">
            </label>
            <p id="pm-retention-review-help">
                Standard sind jeweils 180 Tage. Nach Fristablauf wird ein REVIEW erforderlich. Keine automatische Löschung.
            </p>
        </fieldset>
        <button type="submit">Speichern</button>
    </form>
    <div id="pm-admin-notice" class="pm-notice" role="status" aria-live="polite" aria-atomic="true" hidden></div>
</div>
