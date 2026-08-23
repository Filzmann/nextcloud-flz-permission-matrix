<?php
script('filzmann_permission_matrix', 'modules/api');
script('filzmann_permission_matrix', 'admin');
style('filzmann_permission_matrix', 'style');
$config = $_['config'];
?>

<div id="pm-admin" class="pm-admin">
    <h2>Berechtigungsmatrix</h2>
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
        <button type="submit">Speichern</button>
    </form>
    <div id="pm-admin-notice" class="pm-notice" role="status" aria-live="polite" aria-atomic="true" hidden></div>
</div>
