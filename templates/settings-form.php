<div id="pm-admin" class="pm-admin">
    <h2><?php p($l->t('Berechtigungsmatrix')); ?></h2>
    <form id="pm-admin-form">
        <p class="pm-notice"><?php p($l->t('
            Die Rechtezuordnung wird nicht in dieser App geändert: BR-Mitglieder dürfen Matrix,
            Exporte und manuelle Scans verwenden. Datenschutzbeauftragte dürfen zusätzlich
            Baselines und diese App-Konfiguration verwalten.
        ')); ?></p>
        <label><?php p($l->t('
            Scan-Intervall
            ')); ?><select name="scan_interval">
                <?php foreach (['hourly' => 'stündlich', 'daily' => 'täglich', 'weekly' => 'wöchentlich'] as $value => $label): ?>
                    <option value="<?php p($value); ?>" <?php if ($config['scan_interval'] === $value) { print_unescaped('selected'); } ?>><?php p($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><input type="checkbox" name="strict_mode" value="1" <?php if ($config['strict_mode']) { print_unescaped('checked'); } ?>><?php p($l->t(' Strict Mode')); ?></label>
        <label><input type="checkbox" name="include_users" value="1" <?php if ($config['include_users']) { print_unescaped('checked'); } ?>><?php p($l->t(' Benutzerlisten in Exporten')); ?></label>
        <label><input type="checkbox" name="redact_paths" value="1" <?php if ($config['redact_paths']) { print_unescaped('checked'); } ?>><?php p($l->t(' Pfade redigieren')); ?></label>
        <label><input type="checkbox" name="include_share_metadata" value="1" <?php if ($config['include_share_metadata']) { print_unescaped('checked'); } ?>><?php p($l->t(' Share-Metadaten aufnehmen')); ?></label>
        <label><?php p($l->t('
            Exportformate
            ')); ?><input type="text" name="export_formats" value="<?php p(implode(',', $config['export_formats'])); ?>">
        </label>
        <label><?php p($l->t('
            Snapshot-Retention
            ')); ?><input type="number" min="1" max="500" name="retention" value="<?php p((string)$config['retention']); ?>">
        </label>
        <fieldset>
            <legend><?php p($l->t('Datenschutz-REVIEW')); ?></legend>
            <label><?php p($l->t('
                Aufbewahrungsfrist für Exportmetadaten in Tagen
                ')); ?><input type="number" min="1" max="3650" name="export_metadata_retention_days" value="<?php p((string)$config['export_metadata_retention_days']); ?>" aria-describedby="pm-retention-review-help">
            </label>
            <label><?php p($l->t('
                Aufbewahrungsfrist für Auditprotokolle in Tagen
                ')); ?><input type="number" min="1" max="3650" name="audit_retention_days" value="<?php p((string)$config['audit_retention_days']); ?>" aria-describedby="pm-retention-review-help">
            </label>
            <p id="pm-retention-review-help"><?php p($l->t('
                Standard sind jeweils 180 Tage. Nach Fristablauf wird ein REVIEW erforderlich. Keine automatische Löschung.
            ')); ?></p>
        </fieldset>
        <button type="submit"><?php p($l->t('Speichern')); ?></button>
    </form>
    <div id="pm-admin-notice" class="pm-notice" role="status" aria-live="polite" aria-atomic="true" hidden></div>
</div>
