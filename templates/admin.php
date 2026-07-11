<?php
script('br_permission_matrix', 'modules/api');
script('br_permission_matrix', 'admin');
style('br_permission_matrix', 'style');
$config = $_['config'];
?>

<div id="pm-admin" class="pm-admin">
    <h2>Berechtigungsmatrix</h2>
    <form id="pm-admin-form">
        <label>
            Viewer-Gruppen
            <textarea name="viewer_groups" rows="4"><?php p(implode("\n", $config['viewer_groups'])); ?></textarea>
        </label>
        <label>
            Admin-Gruppen
            <textarea name="admin_groups" rows="3"><?php p(implode("\n", $config['admin_groups'])); ?></textarea>
        </label>
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
